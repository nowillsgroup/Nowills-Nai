<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Application;
use App\Models\ArenaConnection;
use App\Models\Conversation;
use App\Models\DatabaseConnection;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\Project;
use App\Models\Repository;
use App\Models\ResourceCheck;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Services\ImportReviewStore;
use App\Services\WorkspaceExporter;
use App\Services\WorkspaceImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class WorkspaceImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::create(['name' => 'Import Admin', 'email' => 'import-admin@test.local', 'password' => 'password-123', 'role' => 'admin']);
    }

    private function snapshot(array $overrides = []): array
    {
        return array_replace([
            'format_version' => 1, 'platform' => 'NAi', 'workspace' => 'Source workspace', 'exported_at' => '2026-11-08T08:00:00Z',
            'applications' => [], 'repositories' => [], 'databases' => [], 'infrastructure' => [], 'projects' => [],
        ], $overrides);
    }

    private function application(array $overrides = []): array
    {
        return array_replace(['id' => 900, 'name' => 'Imported Portal', 'slug' => 'imported-portal', 'environment' => 'production', 'status' => 'healthy', 'health_score' => 99, 'last_checked_at' => '2026-11-08T07:00:00Z', 'tech_stack' => ['Laravel', 'MySQL']], $overrides);
    }

    private function preview(array $snapshot, string $filename = 'backup.json'): array
    {
        return app(WorkspaceImporter::class)->preview(json_encode($snapshot, JSON_THROW_ON_ERROR), $filename);
    }

    private function upload(array $snapshot): array
    {
        $response = $this->actingAs($this->admin)->post('/settings/import/preview', [
            'snapshot' => UploadedFile::fake()->createWithContent('backup.json', json_encode($snapshot, JSON_THROW_ON_ERROR)),
        ])->assertOk();
        preg_match('/name="review" value="([A-Za-z0-9]{64})"/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        return [$response, $matches[1]];
    }

    public function test_import_page_is_admin_only_and_upload_alone_writes_nothing(): void
    {
        $this->get('/settings/import')->assertRedirect('/login');
        foreach (['operator', 'viewer'] as $role) {
            $user = User::create(['name' => ucfirst($role), 'email' => $role.'@test.local', 'password' => 'password-123', 'role' => $role]);
            $this->actingAs($user)->get('/settings/import')->assertForbidden();
            $this->actingAs($user)->post('/settings/import/preview')->assertForbidden();
            $this->actingAs($user)->post('/settings/import/apply')->assertForbidden();
            $this->actingAs($user)->post('/settings/import/cancel')->assertForbidden();
        }
        [$response] = $this->upload($this->snapshot(['applications' => [$this->application()]]));
        $response->assertSee('Your change plan')->assertSee('Imported Portal')->assertSee('Apply reviewed import')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('applications', 0);
        $this->assertDatabaseCount('activities', 0);
    }

    public function test_reviews_are_encrypted_private_and_bound_to_the_uploading_admin(): void
    {
        [, $token] = $this->upload($this->snapshot(['applications' => [$this->application()]]));
        $files = Storage::disk('local')->files('import-reviews');
        $this->assertCount(1, $files);
        $raw = Storage::disk('local')->get($files[0]);
        $this->assertStringNotContainsString('Imported Portal', $raw);
        $this->assertStringNotContainsString($token, $files[0]);
        $this->assertStringStartsWith('import-reviews/', $files[0]);
        $reviews = app(ImportReviewStore::class);
        $other = User::create(['name' => 'Other Admin', 'email' => 'other-admin@test.local', 'password' => 'password-123', 'role' => 'admin']);
        $this->assertNull($reviews->peek($token, $other->id));
        $this->assertNotNull($reviews->peek($token, $this->admin->id));
        $this->actingAs($other)->post('/settings/import/apply', ['review' => $token, 'confirmation' => '1'])->assertRedirect('/settings/import');
        $this->assertDatabaseCount('applications', 0);
        $this->assertNotNull($reviews->peek($token, $this->admin->id));
    }

    public function test_apply_requires_explicit_confirmation_and_consumes_the_review_once(): void
    {
        [, $token] = $this->upload($this->snapshot(['applications' => [$this->application()]]));
        $this->actingAs($this->admin)->post('/settings/import/apply', ['review' => $token])->assertSessionHasErrors('confirmation');
        $this->assertDatabaseCount('applications', 0);
        $this->actingAs($this->admin)->post('/settings/import/apply', ['review' => $token, 'confirmation' => '1'])->assertRedirect('/settings/import')->assertSessionHas('success');
        $this->assertDatabaseCount('applications', 1);
        $this->actingAs($this->admin)->post('/settings/import/apply', ['review' => $token, 'confirmation' => '1'])->assertSessionHasErrors('review');
        $this->assertDatabaseCount('applications', 1);
        $this->assertSame([], Storage::disk('local')->files('import-reviews'));
        $audit = Activity::where('action', 'workspace.imported')->firstOrFail();
        $this->assertSame($this->admin->id, $audit->user_id);
        $this->assertSame(1, $audit->meta['counts']['applications']);
    }

    public function test_full_import_remaps_relationships_and_excludes_identities_secrets_and_history(): void
    {
        $existing = Application::create(['name' => 'Existing App', 'slug' => 'existing-app', 'environment' => 'production', 'status' => 'healthy', 'owner_id' => $this->admin->id]);
        Setting::create(['key' => 'workspace_name', 'value' => 'Destination workspace']);
        ArenaConnection::create(['base_url' => 'https://destination.example.test', 'api_key' => 'destination-secret', 'status' => 'connected']);
        Conversation::create(['user_id' => $this->admin->id, 'title' => 'Keep this conversation']);
        $snapshot = $this->snapshot([
            'applications' => [$this->application()],
            'repositories' => [['id' => 901, 'name' => 'Imported Repo', 'url' => 'https://github.com/example/portal/', 'provider' => 'github', 'default_branch' => 'main', 'visibility' => 'private', 'status' => 'active', 'application_id' => 900, 'last_synced_at' => '2026-11-08T07:00:00Z']],
            'databases' => [['id' => 902, 'name' => 'Imported DB', 'engine' => 'mysql', 'host' => 'db.example.test', 'port' => 3306, 'environment' => 'production', 'size_mb' => '42.50', 'application_id' => 900, 'status' => 'online', 'backup_status' => 'current', 'last_backup_at' => '2026-11-08T07:00:00Z', 'password' => 'source-db-secret']],
            'infrastructure' => [['id' => 903, 'name' => 'Imported Server', 'type' => 'server', 'provider' => 'Example Cloud', 'environment' => 'production', 'ip_address' => '203.0.113.10', 'application_id' => 900, 'status' => 'online']],
            'projects' => [['id' => 904, 'name' => 'Imported Project', 'key' => 'IMP', 'status' => 'active', 'priority' => 'high', 'owner_id' => 777, 'start_date' => '2026-11-01', 'due_date' => '2026-12-01', 'tasks' => [['id' => 905, 'title' => 'Imported task', 'description' => 'Check the restore', 'status' => 'backlog', 'priority' => 'medium', 'assignee_id' => 777, 'source_message_id' => 888]]]],
            'users' => [['id' => 777, 'name' => 'Source User', 'email' => 'source-user@example.test', 'password' => 'source-password', 'role' => 'admin']],
            'arena_connection' => ['base_url' => 'https://source.example.test', 'api_key' => 'source-arena-secret'],
            'settings' => ['workspace_name' => 'Source workspace'],
            'conversations' => [['title' => 'Source conversation']],
            'monitoring' => ['open_incidents' => [['status' => 'open']], 'checks' => [['status' => 'healthy']]],
        ]);
        $snapshot['applications'][0]['owner_id'] = 777;
        $plan = $this->preview($snapshot);
        $this->assertTrue($plan['can_apply']);
        $this->assertSame(6, $plan['totals']['new']);
        app(WorkspaceImporter::class)->apply($plan);
        $app = Application::where('slug', 'imported-portal')->firstOrFail();
        $this->assertNotSame(900, $app->id);
        $this->assertSame('unknown', $app->status);
        $this->assertNull($app->health_score);
        $this->assertNull($app->last_checked_at);
        $this->assertNull($app->owner_id);
        $this->assertSame($app->id, Repository::firstOrFail()->application_id);
        $this->assertNull(Repository::first()->last_synced_at);
        $db = DatabaseConnection::firstOrFail();
        $this->assertSame($app->id, $db->application_id);
        $this->assertSame('unknown', $db->status);
        $this->assertSame('not_configured', $db->backup_status);
        $this->assertNull($db->last_backup_at);
        $this->assertSame('42.50', $db->size_mb);
        $this->assertSame('unknown', Infrastructure::firstOrFail()->status);
        $project = Project::firstOrFail();
        $this->assertNull($project->owner_id);
        $task = Task::firstOrFail();
        $this->assertSame($project->id, $task->project_id);
        $this->assertNull($task->assignee_id);
        $this->assertNull($task->source_message_id);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('resource_checks', 0);
        $this->assertDatabaseCount('incidents', 0);
        $this->assertSame('Destination workspace', Setting::where('key', 'workspace_name')->value('value'));
        $this->assertSame('destination-secret', ArenaConnection::firstOrFail()->api_key);
        $this->assertSame('healthy', $existing->fresh()->status);
        $this->assertSame($this->admin->id, $existing->fresh()->owner_id);
    }

    public function test_existing_operational_health_does_not_cause_a_metadata_conflict(): void
    {
        Application::create(['name' => 'Imported Portal', 'slug' => 'imported-portal', 'environment' => 'production', 'status' => 'healthy', 'health_score' => 99, 'tech_stack' => ['Laravel', 'MySQL']]);
        $plan = $this->preview($this->snapshot(['applications' => [$this->application()]]));
        $this->assertSame(1, $plan['totals']['existing']);
        $this->assertSame(0, $plan['totals']['conflicts']);
        $this->assertFalse($plan['can_apply']);
        $this->assertSame('healthy', Application::firstOrFail()->status);
    }

    public function test_conflicts_block_the_entire_import_without_partial_writes(): void
    {
        Application::create(['name' => 'Different Name', 'slug' => 'imported-portal', 'environment' => 'production', 'status' => 'unknown']);
        $plan = $this->preview($this->snapshot(['applications' => [$this->application(), $this->application(['id' => 901, 'name' => 'Another App', 'slug' => 'another-app'])]]));
        $this->assertSame(1, $plan['totals']['conflicts']);
        $this->assertSame(1, $plan['totals']['new']);
        $this->assertFalse($plan['can_apply']);
        $this->expectException(RuntimeException::class);
        try { app(WorkspaceImporter::class)->apply($plan); }
        finally { $this->assertDatabaseCount('applications', 1); $this->assertSame('Different Name', Application::firstOrFail()->name); }
    }

    public function test_ambiguous_existing_repository_identities_are_blocked(): void
    {
        foreach (['One', 'Two'] as $name) Repository::create(['name' => $name, 'url' => 'https://github.com/example/portal', 'provider' => 'github', 'visibility' => 'private', 'status' => 'active']);
        $plan = $this->preview($this->snapshot(['repositories' => [['name' => 'Source Repo', 'url' => 'https://github.com/example/portal/', 'provider' => 'github', 'visibility' => 'private', 'status' => 'active']]]));
        $this->assertSame(1, $plan['totals']['conflicts']);
        $this->assertFalse($plan['can_apply']);
        $this->assertStringContainsString('Multiple existing records', $plan['sections']['repositories']['rows'][0]['reason']);
    }

    public function test_stale_previews_are_revalidated_and_cannot_overwrite_new_changes(): void
    {
        [, $token] = $this->upload($this->snapshot(['applications' => [$this->application()]]));
        Application::create(['name' => 'Created After Preview', 'slug' => 'imported-portal', 'environment' => 'production', 'status' => 'unknown']);
        $this->actingAs($this->admin)->post('/settings/import/apply', ['review' => $token, 'confirmation' => '1'])->assertSessionHasErrors('review');
        $this->assertSame('Created After Preview', Application::firstOrFail()->name);
        $this->assertDatabaseCount('applications', 1);
        $this->assertNull(app(ImportReviewStore::class)->peek($token, $this->admin->id));
    }

    public function test_cancel_and_expiry_never_write_workspace_records(): void
    {
        [, $token] = $this->upload($this->snapshot(['applications' => [$this->application()]]));
        $this->actingAs($this->admin)->post('/settings/import/cancel', ['review' => $token])->assertRedirect('/settings/import');
        $this->assertDatabaseCount('applications', 0);
        $this->assertNull(app(ImportReviewStore::class)->peek($token, $this->admin->id));
        [, $expired] = $this->upload($this->snapshot(['applications' => [$this->application()]]));
        $this->travel(16)->minutes();
        $this->assertNull(app(ImportReviewStore::class)->pull($expired, $this->admin->id));
        $this->assertSame(1, app(ImportReviewStore::class)->prune());
        $this->assertSame([], Storage::disk('local')->files('import-reviews'));
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_tampered_reviews_are_rejected(): void
    {
        [, $token] = $this->upload($this->snapshot(['applications' => [$this->application()]]));
        $path = Storage::disk('local')->files('import-reviews')[0];
        $envelope = json_decode(Crypt::decryptString(Storage::disk('local')->get($path)), true);
        $envelope['plan']['totals']['new'] = 999;
        Storage::disk('local')->put($path, Crypt::encryptString(json_encode($envelope)));
        $this->assertNull(app(ImportReviewStore::class)->peek($token, $this->admin->id));
        $this->actingAs($this->admin)->post('/settings/import/apply', ['review' => $token, 'confirmation' => '1'])->assertSessionHasErrors('review');
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_invalid_files_versions_links_and_limits_are_rejected_without_writes(): void
    {
        $importer = app(WorkspaceImporter::class);
        $this->assertFalse($importer->preview('{bad json')['can_apply']);
        $this->assertFalse($importer->preview(str_repeat(' ', WorkspaceImporter::MAX_BYTES + 1))['can_apply']);
        $cases = [
            $this->snapshot(['format_version' => 2]),
            $this->snapshot(['platform' => 'Other']),
            $this->snapshot(['applications' => 'not an array']),
            $this->snapshot(['applications' => [$this->application(['environment' => 'invalid'])]]),
            $this->snapshot(['applications' => [$this->application(), $this->application()]]),
            $this->snapshot(['applications' => [$this->application(), $this->application(['id' => 900, 'slug' => 'second-app'])]]),
            $this->snapshot(['repositories' => [['name' => 'Repo', 'url' => 'https://github.com/example/repo', 'provider' => 'github', 'visibility' => 'private', 'status' => 'active', 'application_id' => 999]]]),
            $this->snapshot(['applications' => array_fill(0, 501, $this->application())]),
            $this->snapshot(['projects' => [['name' => 'Project', 'key' => 'P', 'status' => 'active', 'priority' => 'high', 'start_date' => '2026-12-01', 'due_date' => '2026-11-01', 'tasks' => []]]]),
        ];
        foreach ($cases as $snapshot) { $plan = $this->preview($snapshot); $this->assertFalse($plan['can_apply']); $this->assertNotEmpty($plan['errors']); }
        $this->assertDatabaseCount('applications', 0);
        $this->assertDatabaseCount('repositories', 0);
    }

    public function test_embedded_url_credentials_and_duplicate_tasks_are_rejected(): void
    {
        $plan = $this->preview($this->snapshot(['applications' => [$this->application(['url' => 'https://user:password@example.test'])]]));
        $this->assertFalse($plan['can_apply']);
        $plan = $this->preview($this->snapshot(['repositories' => [['name' => 'Repo', 'url' => 'https://user:password@github.com/example/repo', 'provider' => 'github', 'visibility' => 'private', 'status' => 'active']]]));
        $this->assertFalse($plan['can_apply']);
        $this->assertStringContainsString('embedded credentials', implode(' ', array_column($plan['errors'], 'message')));
        $plan = $this->preview($this->snapshot(['projects' => [['name' => 'Project', 'key' => 'P', 'status' => 'active', 'priority' => 'high', 'due_date' => 'tomorrow', 'tasks' => []]]]));
        $this->assertFalse($plan['can_apply']);
        $task = ['title' => 'Same task', 'description' => 'Same description', 'status' => 'backlog', 'priority' => 'medium'];
        $plan = $this->preview($this->snapshot(['projects' => [['name' => 'Project', 'key' => 'P', 'status' => 'active', 'priority' => 'high', 'tasks' => [$task, $task]]]]));
        $this->assertFalse($plan['can_apply']);
        $this->assertStringContainsString('duplicate task', implode(' ', array_column($plan['errors'], 'message')));
    }

    public function test_transaction_rolls_back_all_created_records_on_failure(): void
    {
        $plan = $this->preview($this->snapshot([
            'applications' => [$this->application()],
            'projects' => [['name' => 'Project', 'key' => 'P', 'status' => 'active', 'priority' => 'high', 'tasks' => [['title' => 'Task', 'status' => 'backlog', 'priority' => 'medium']]]],
        ]));
        Task::creating(function () { throw new RuntimeException('Simulated write failure'); });
        try {
            app(WorkspaceImporter::class)->apply($plan);
            $this->fail('Expected the import to fail.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated write failure', $e->getMessage());
        } finally { Task::flushEventListeners(); }
        $this->assertDatabaseCount('applications', 0);
        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseCount('activities', 0);
    }

    public function test_cli_defaults_to_dry_run_and_requires_explicit_apply(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'nai-import-test-');
        file_put_contents($path, json_encode($this->snapshot(['applications' => [$this->application()]])));
        try {
            $this->artisan('nai:import', ['path' => $path])->assertSuccessful();
            $this->assertDatabaseCount('applications', 0);
            $this->artisan('nai:import', ['path' => $path, '--dry-run' => true, '--apply' => true])->assertFailed();
            $this->artisan('nai:import', ['path' => $path, '--apply' => true])->assertSuccessful();
            $this->assertDatabaseCount('applications', 1);
            $this->artisan('nai:import', ['path' => $path, '--apply' => true])->assertFailed();
            $this->assertDatabaseCount('applications', 1);
        } finally { unlink($path); }
    }

    public function test_versioned_export_round_trips_as_unchanged_and_rejects_unversioned_files(): void
    {
        Application::create(['name' => 'Export App', 'slug' => 'export-app', 'environment' => 'production', 'status' => 'healthy', 'tech_stack' => ['PHP']]);
        $snapshot = app(WorkspaceExporter::class)->snapshot();
        $this->assertSame(1, $snapshot['format_version']);
        $plan = $this->preview($snapshot);
        $this->assertSame(1, $plan['totals']['existing']);
        $this->assertSame(0, $plan['totals']['conflicts']);
        $this->assertEmpty($plan['errors']);
        $this->assertFalse($plan['can_apply']);
        unset($snapshot['format_version']);
        $this->assertFalse($this->preview($snapshot)['can_apply']);
        $response = $this->actingAs($this->admin)->get('/settings/export')->assertOk();
        $this->assertSame(1, json_decode($response->streamedContent(), true)['format_version']);
    }

    public function test_database_failures_do_not_leak_sql_details_to_the_browser(): void
    {
        config(['logging.default' => 'null']);
        [, $token] = $this->upload($this->snapshot(['applications' => [$this->application()]]));
        $hash = app(WorkspaceImporter::class)->hash(app(ImportReviewStore::class)->peek($token, $this->admin->id));
        $this->mock(WorkspaceImporter::class, function ($mock) use ($hash) {
            $mock->shouldReceive('hash')->andReturn($hash);
            $mock->shouldReceive('apply')->once()->andThrow(new \Illuminate\Database\QueryException('sqlite', 'insert into secret_table values (?)', ['sensitive-value'], new \Exception('raw sql failure')));
        });
        $this->actingAs($this->admin)->post('/settings/import/apply', ['review' => $token, 'confirmation' => '1'])
            ->assertRedirect('/settings/import')
            ->assertSessionHasErrors('review');
        $error = session('errors')->first('review');
        $this->assertStringContainsString('no changes were saved', $error);
        $this->assertStringNotContainsString('secret_table', $error);
        $this->assertStringNotContainsString('sensitive-value', $error);
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_review_names_are_escaped_and_invalid_uploads_return_validation_errors(): void
    {
        [$response, $token] = $this->upload($this->snapshot(['applications' => [$this->application(['name' => '<script>alert(1)</script>'])]]));
        $this->actingAs($this->admin)->post('/settings/import/cancel', ['review' => $token])->assertRedirect();
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->actingAs($this->admin)->post('/settings/import/preview', ['snapshot' => UploadedFile::fake()->createWithContent('backup.txt', '{}')])->assertSessionHasErrors('snapshot');
        $this->assertSame([], Storage::disk('local')->files('import-reviews'));
        $this->assertDatabaseCount('applications', 0);
    }
}
