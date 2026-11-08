<?php

namespace App\Http\Controllers;

use App\Services\ImportReviewStore;
use App\Services\WorkspaceImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\File;
use RuntimeException;

class ImportController extends Controller
{
    public function index()
    {
        return view('settings.import', ['plan' => null, 'token' => null]);
    }

    public function preview(Request $request, WorkspaceImporter $importer, ImportReviewStore $reviews)
    {
        $request->validate([
            'snapshot' => ['required', File::types(['json'])->max(WorkspaceImporter::MAX_BYTES / 1024)],
        ], [
            'snapshot.required' => 'Choose a NAi workspace JSON snapshot.',
            'snapshot.file' => 'The upload failed. Check the file and your PHP upload size limit.',
            'snapshot.mimes' => 'Upload a .json workspace snapshot.',
            'snapshot.max' => 'The snapshot exceeds the 5 MB upload limit.',
        ]);
        $file = $request->file('snapshot');
        $json = file_get_contents($file->getRealPath());
        if ($json === false) return back()->withErrors(['snapshot' => 'The uploaded snapshot could not be read.']);
        try {
            $plan = $importer->preview($json, $file->getClientOriginalName());
            $token = $reviews->put($plan, $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['snapshot' => $e->getMessage()]);
        }
        return response()->view('settings.import', compact('plan', 'token'))->header('Cache-Control', 'no-store, private');
    }

    public function apply(Request $request, WorkspaceImporter $importer, ImportReviewStore $reviews)
    {
        $data = $request->validate([
            'review' => 'required|string|size:64|alpha_num',
            'confirmation' => 'accepted',
        ], [
            'review.required' => 'This review is no longer available. Upload the snapshot again.',
            'confirmation.accepted' => 'Confirm the reviewed additions before applying the import.',
        ]);
        $plan = $reviews->pull($data['review'], $request->user()->id);
        if ($plan === null) return redirect()->route('settings.import')->withErrors(['review' => 'This review expired, was already used, or belongs to another administrator. Upload the snapshot again.']);
        try {
            $counts = $importer->apply($plan);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Workspace import failed.', ['exception' => $e]);
            return redirect()->route('settings.import')->withErrors(['review' => 'The import could not be completed and no changes were saved. Upload the snapshot again to review a fresh plan.']);
        } catch (RuntimeException $e) {
            return redirect()->route('settings.import')->withErrors(['review' => $e->getMessage()]);
        }
        $parts = [];
        foreach (WorkspaceImporter::SECTIONS as $type => $label) if ($counts[$type]) $parts[] = $counts[$type].' '.strtolower($label);
        return redirect()->route('settings.import')->with('success', $parts ? 'Import complete. Created '.implode(', ', $parts).'. Existing records were left unchanged.' : 'Import complete. No new records were needed.');
    }

    public function cancel(Request $request, ImportReviewStore $reviews)
    {
        $data = $request->validate(['review' => 'required|string|size:64|alpha_num']);
        // Only the owning administrator can cancel a review.
        if ($reviews->peek($data['review'], $request->user()->id) !== null) $reviews->forget($data['review']);
        return redirect()->route('settings.import')->with('success', 'Import cancelled. No workspace records were changed.');
    }
}
