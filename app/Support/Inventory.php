<?php

namespace App\Support;

use App\Models\Application;
use App\Models\DatabaseConnection;
use App\Models\Infrastructure;
use App\Models\Repository;

class Inventory
{
    public static function types(): array
    {
        return [
            'applications' => [
                'label' => 'Applications', 'singular' => 'Application', 'model' => Application::class,
                'icon' => 'apps', 'route' => 'inventory.index', 'description' => 'Software products and services across the group.',
                'columns' => ['name', 'environment', 'status', 'owner', 'url'],
                'fields' => [
                    'name' => ['label' => 'Application name', 'type' => 'text', 'required' => true, 'placeholder' => 'Nowills Customer Portal'],
                    'description' => ['label' => 'Description', 'type' => 'textarea', 'placeholder' => 'What this application does and who it serves'],
                    'url' => ['label' => 'Application URL', 'type' => 'url', 'placeholder' => 'https://portal.nowills.group'],
                    'environment' => ['label' => 'Environment', 'type' => 'select', 'required' => true, 'options' => ['production' => 'Production', 'staging' => 'Staging', 'development' => 'Development']],
                    'status' => ['label' => 'Health status', 'type' => 'select', 'required' => true, 'options' => ['healthy' => 'Healthy', 'degraded' => 'Degraded', 'maintenance' => 'Maintenance', 'offline' => 'Offline', 'unknown' => 'Unknown']],
                    'owner_id' => ['label' => 'Owner', 'type' => 'owner', 'options' => []],
                    'tech_stack' => ['label' => 'Technology stack', 'type' => 'text', 'placeholder' => 'Laravel, MySQL, Redis', 'help' => 'Comma-separated technologies.'],
                    'health_score' => ['label' => 'Health score', 'type' => 'number', 'placeholder' => '98', 'help' => '0–100. Leave blank if not measured.'],
                    'notes' => ['label' => 'Operational notes', 'type' => 'textarea'],
                ],
            ],
            'repositories' => [
                'label' => 'Repositories', 'singular' => 'Repository', 'model' => Repository::class,
                'icon' => 'git', 'route' => 'inventory.index', 'description' => 'Source code repositories and development assets.',
                'columns' => ['name', 'provider', 'language', 'branch', 'status'],
                'fields' => [
                    'name' => ['label' => 'Repository name', 'type' => 'text', 'required' => true, 'placeholder' => 'nowills/customer-portal'],
                    'description' => ['label' => 'Description', 'type' => 'textarea'],
                    'url' => ['label' => 'Repository URL', 'type' => 'url', 'required' => true, 'placeholder' => 'https://github.com/nowillsgroup/customer-portal'],
                    'provider' => ['label' => 'Provider', 'type' => 'select', 'required' => true, 'options' => ['github' => 'GitHub', 'gitlab' => 'GitLab', 'bitbucket' => 'Bitbucket', 'azure_devops' => 'Azure DevOps', 'self_hosted' => 'Self-hosted']],
                    'default_branch' => ['label' => 'Default branch', 'type' => 'text', 'placeholder' => 'main'],
                    'language' => ['label' => 'Primary language', 'type' => 'text', 'placeholder' => 'PHP'],
                    'visibility' => ['label' => 'Visibility', 'type' => 'select', 'required' => true, 'options' => ['private' => 'Private', 'internal' => 'Internal', 'public' => 'Public']],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['active' => 'Active', 'syncing' => 'Syncing', 'archived' => 'Archived']],
                    'application_id' => ['label' => 'Linked application', 'type' => 'application', 'options' => []],
                ],
            ],
            'databases' => [
                'label' => 'Databases', 'singular' => 'Database', 'model' => DatabaseConnection::class,
                'icon' => 'database', 'route' => 'inventory.index', 'description' => 'Database inventory and backup posture. Credentials are never stored here.',
                'columns' => ['name', 'engine', 'host', 'environment', 'status'],
                'fields' => [
                    'name' => ['label' => 'Database name', 'type' => 'text', 'required' => true, 'placeholder' => 'portal_production'],
                    'engine' => ['label' => 'Engine', 'type' => 'select', 'required' => true, 'options' => ['mysql' => 'MySQL', 'mariadb' => 'MariaDB', 'postgresql' => 'PostgreSQL', 'sqlserver' => 'SQL Server', 'mongodb' => 'MongoDB', 'redis' => 'Redis']],
                    'version' => ['label' => 'Version', 'type' => 'text', 'placeholder' => '8.4'],
                    'host' => ['label' => 'Host', 'type' => 'text', 'required' => true, 'placeholder' => 'db.internal.nowills.group'],
                    'port' => ['label' => 'Port', 'type' => 'number', 'placeholder' => '3306'],
                    'environment' => ['label' => 'Environment', 'type' => 'select', 'required' => true, 'options' => ['production' => 'Production', 'staging' => 'Staging', 'development' => 'Development']],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['online' => 'Online', 'degraded' => 'Degraded', 'maintenance' => 'Maintenance', 'offline' => 'Offline']],
                    'size_mb' => ['label' => 'Size (MB)', 'type' => 'number', 'placeholder' => '2048'],
                    'backup_status' => ['label' => 'Backup posture', 'type' => 'select', 'required' => true, 'options' => ['current' => 'Current', 'due' => 'Due', 'failed' => 'Failed', 'not_configured' => 'Not configured']],
                    'application_id' => ['label' => 'Linked application', 'type' => 'application', 'options' => []],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea', 'help' => 'Metadata only. Do not enter passwords or connection secrets.'],
                ],
            ],
            'infrastructure' => [
                'label' => 'Infrastructure', 'singular' => 'Infrastructure', 'model' => Infrastructure::class,
                'icon' => 'server', 'route' => 'inventory.index', 'description' => 'Hosting environments, servers, and technical infrastructure.',
                'columns' => ['name', 'provider', 'region', 'environment', 'status'],
                'fields' => [
                    'name' => ['label' => 'Resource name', 'type' => 'text', 'required' => true, 'placeholder' => 'Production web cluster'],
                    'type' => ['label' => 'Resource type', 'type' => 'select', 'required' => true, 'options' => ['server' => 'Server', 'hosting' => 'Hosting platform', 'load_balancer' => 'Load balancer', 'storage' => 'Storage', 'network' => 'Network', 'cdn' => 'CDN']],
                    'provider' => ['label' => 'Provider', 'type' => 'text', 'required' => true, 'placeholder' => 'AWS, Hetzner, DigitalOcean…'],
                    'region' => ['label' => 'Region', 'type' => 'text', 'placeholder' => 'eu-west-1'],
                    'hostname' => ['label' => 'Hostname', 'type' => 'text', 'placeholder' => 'web-01.nowills.group'],
                    'ip_address' => ['label' => 'IP address', 'type' => 'text', 'placeholder' => '10.0.1.10'],
                    'environment' => ['label' => 'Environment', 'type' => 'select', 'required' => true, 'options' => ['production' => 'Production', 'staging' => 'Staging', 'development' => 'Development']],
                    'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['online' => 'Online', 'degraded' => 'Degraded', 'maintenance' => 'Maintenance', 'offline' => 'Offline']],
                    'specs' => ['label' => 'Specifications', 'type' => 'text', 'placeholder' => '8 vCPU · 32 GB RAM · 500 GB SSD'],
                    'application_id' => ['label' => 'Linked application', 'type' => 'application', 'options' => []],
                    'ssl_expires_at' => ['label' => 'SSL expiry', 'type' => 'date'],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea'],
                ],
            ],
        ];
    }

    public static function type(string $key): array
    {
        abort_unless(isset(self::types()[$key]), 404);
        return self::types()[$key];
    }

    public static function rules(string $key): array
    {
        $base = [
            'applications' => ['name' => 'required|string|max:160', 'description' => 'nullable|string|max:2000', 'url' => 'nullable|url|max:500', 'environment' => 'required|in:production,staging,development', 'status' => 'required|in:healthy,degraded,maintenance,offline,unknown', 'owner_id' => 'nullable|exists:users,id', 'tech_stack' => 'nullable|string|max:500', 'health_score' => 'nullable|integer|min:0|max:100', 'notes' => 'nullable|string|max:5000'],
            'repositories' => ['name' => 'required|string|max:160', 'description' => 'nullable|string|max:2000', 'url' => 'required|url|max:500', 'provider' => 'required|in:github,gitlab,bitbucket,azure_devops,self_hosted', 'default_branch' => 'nullable|string|max:120', 'language' => 'nullable|string|max:80', 'visibility' => 'required|in:private,internal,public', 'status' => 'required|in:active,syncing,archived', 'application_id' => 'nullable|exists:applications,id'],
            'databases' => ['name' => 'required|string|max:160', 'engine' => 'required|in:mysql,mariadb,postgresql,sqlserver,mongodb,redis', 'version' => 'nullable|string|max:40', 'host' => 'required|string|max:255', 'port' => 'nullable|integer|min:1|max:65535', 'environment' => 'required|in:production,staging,development', 'status' => 'required|in:online,degraded,maintenance,offline', 'size_mb' => 'nullable|numeric|min:0', 'backup_status' => 'required|in:current,due,failed,not_configured', 'application_id' => 'nullable|exists:applications,id', 'notes' => 'nullable|string|max:5000'],
            'infrastructure' => ['name' => 'required|string|max:160', 'type' => 'required|in:server,hosting,load_balancer,storage,network,cdn', 'provider' => 'required|string|max:120', 'region' => 'nullable|string|max:120', 'hostname' => 'nullable|string|max:255', 'ip_address' => 'nullable|ip', 'environment' => 'required|in:production,staging,development', 'status' => 'required|in:online,degraded,maintenance,offline', 'specs' => 'nullable|string|max:500', 'application_id' => 'nullable|exists:applications,id', 'ssl_expires_at' => 'nullable|date', 'notes' => 'nullable|string|max:5000'],
        ];
        return $base[$key];
    }
}
