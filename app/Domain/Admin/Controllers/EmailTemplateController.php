<?php

declare(strict_types=1);

namespace App\Domain\Admin\Controllers;

use App\Domain\Admin\Models\EmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class EmailTemplateController extends Controller
{
    public function index(): View
    {
        $templates = EmailTemplate::query()->orderBy('name')->get();

        return view('admin.email-templates.index', compact('templates'));
    }

    public function edit(EmailTemplate $emailTemplate): View
    {
        return view('admin.email-templates.edit', compact('emailTemplate'));
    }

    public function update(Request $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string'],
            'is_active' => ['boolean'],
        ]);

        $emailTemplate->update([
            'name' => $validated['name'],
            'subject' => $validated['subject'],
            'body_html' => $validated['body_html'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.email-templates.index')
            ->with('success', "Template '{$emailTemplate->name}' updated successfully.");
    }

    public function preview(Request $request, EmailTemplate $emailTemplate): JsonResponse
    {
        $sampleData = [];
        foreach ($emailTemplate->variables ?? [] as $variable) {
            $sampleData[$variable] = match ($variable) {
                'user_name' => 'John Doe',
                'app_name' => 'antaraFLOW',
                'org_name' => 'Demo Organization',
                'login_url' => 'https://antaraflow.test/login',
                'reset_url' => 'https://antaraflow.test/reset/token123',
                'meeting_title' => 'Weekly Standup',
                'meeting_date' => 'March 15, 2026',
                'meeting_url' => 'https://antaraflow.test/meetings/1',
                'action_title' => 'Review quarterly report',
                'due_date' => 'March 20, 2026',
                default => "{{$variable}}",
            };
        }

        return response()->json([
            'subject' => $emailTemplate->renderSubject($sampleData),
            'body' => $emailTemplate->render($sampleData),
        ]);
    }
}
