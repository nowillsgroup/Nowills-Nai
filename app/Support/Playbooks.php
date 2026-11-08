<?php

namespace App\Support;

/**
 * Prompt scaffolds for resource-scoped Arena AI conversations.
 *
 * These are instructions NAi sends TO Arena AI. They are not answers, templates
 * for answers, or a local generation mechanism — Arena AI produces all output.
 */
class Playbooks
{
    public static function all(): array
    {
        return [
            'applications' => [
                'health-review' => [
                    'label' => 'Health review',
                    'description' => 'Prioritised remediation plan for this application',
                    'prompt' => 'Review the operational health of the application "{name}" using the NAi context attached to this request. Explain what its current status and recent probe results indicate, list the diagnostic checks you would run next, and propose a prioritised remediation plan with clear acceptance criteria.',
                ],
                'incident-triage' => [
                    'label' => 'Incident triage',
                    'description' => 'Root-cause hypotheses for current incidents',
                    'prompt' => 'Triage the incidents and recent check history for "{name}" included in the NAi context. Give ranked root-cause hypotheses, the evidence that would confirm or rule out each one, immediate mitigations, and the follow-up work needed to prevent recurrence.',
                ],
                'deployment-plan' => [
                    'label' => 'Deployment plan',
                    'description' => 'Safe release steps, rollback, and verification',
                    'prompt' => 'Draft a deployment plan for "{name}" based on its stack and environment in the NAi context. Include pre-flight checks, migration handling, the release sequence, rollback triggers, and post-deploy verification steps.',
                ],
                'runbook' => [
                    'label' => 'Draft runbook',
                    'description' => 'Operational runbook for the on-call engineer',
                    'prompt' => 'Write an operational runbook for "{name}" using the NAi context: service purpose, dependencies, health endpoints, common failure modes with diagnostic commands, escalation path, and recovery procedures. Flag anything you cannot determine from the supplied context instead of guessing.',
                ],
                'security-review' => [
                    'label' => 'Security review',
                    'description' => 'Threat surface and hardening checklist',
                    'prompt' => 'Perform a security review of "{name}" from the NAi context. Identify the likely threat surface for its stack and environment, list concrete hardening actions ordered by risk, and specify what additional information you need for a deeper assessment.',
                ],
            ],
            'repositories' => [
                'code-review' => [
                    'label' => 'Review strategy',
                    'description' => 'Review focus areas for this codebase',
                    'prompt' => 'Propose a code review strategy for the repository "{name}" using its language, provider, and linked application from the NAi context. List the highest-value review focus areas, the checks to automate in CI, and the review checklist a maintainer should apply.',
                ],
                'release-checklist' => [
                    'label' => 'Release checklist',
                    'description' => 'Branching, versioning, and release gates',
                    'prompt' => 'Create a release checklist for "{name}" covering branching strategy, versioning, CI gates, changelog, artefact publishing, and rollback, tailored to the repository metadata in the NAi context.',
                ],
                'dependency-audit' => [
                    'label' => 'Dependency audit',
                    'description' => 'Supply-chain and upgrade posture',
                    'prompt' => 'Outline a dependency and supply-chain audit for "{name}" based on its primary language in the NAi context. Include tooling, cadence, severity triage rules, and an upgrade policy. State which findings would require data you do not have.',
                ],
            ],
            'databases' => [
                'resilience-review' => [
                    'label' => 'Resilience review',
                    'description' => 'Backup posture and recovery readiness',
                    'prompt' => 'Assess the resilience of the database "{name}" using its engine, environment, size, and backup posture from the NAi context. Identify gaps, propose a backup and restore-verification regime, and define recovery objectives you would recommend.',
                ],
                'migration-plan' => [
                    'label' => 'Migration plan',
                    'description' => 'Low-downtime schema change strategy',
                    'prompt' => 'Design a low-downtime schema migration approach for "{name}" given its engine and version in the NAi context. Cover expand/contract sequencing, locking risks, backfill strategy, verification, and rollback.',
                ],
                'capacity-review' => [
                    'label' => 'Capacity review',
                    'description' => 'Growth, indexing, and scaling actions',
                    'prompt' => 'Review capacity for "{name}" using the size and environment metadata in the NAi context. Recommend indexing and query checks, growth monitoring thresholds, and scaling options, and list the measurements needed before committing to a plan.',
                ],
            ],
            'infrastructure' => [
                'hardening' => [
                    'label' => 'Hardening plan',
                    'description' => 'Configuration and access hardening',
                    'prompt' => 'Produce a hardening plan for the infrastructure resource "{name}" using its type, provider, and region from the NAi context. Cover network exposure, access control, patching, TLS posture, and logging, ordered by risk.',
                ],
                'capacity-review' => [
                    'label' => 'Capacity review',
                    'description' => 'Headroom, scaling, and failure domains',
                    'prompt' => 'Review capacity and resilience for "{name}" from the NAi context. Assess headroom, single points of failure, and scaling options for its specifications, and define the monitoring thresholds you would set.',
                ],
                'cost-review' => [
                    'label' => 'Cost review',
                    'description' => 'Efficiency opportunities without risk',
                    'prompt' => 'Suggest a cost-efficiency review for "{name}" based on its provider, type, and specifications in the NAi context. Separate quick wins from structural changes, and state the utilisation data required before acting.',
                ],
            ],
            'projects' => [
                'delivery-plan' => [
                    'label' => 'Delivery plan',
                    'description' => 'Milestones, sequencing, and dependencies',
                    'prompt' => 'Build a delivery plan for the project "{name}" using its tasks, statuses, priority, and due date from the NAi context. Propose milestones, sequencing, dependencies, and the critical path, and call out schedule risk.',
                ],
                'task-breakdown' => [
                    'label' => 'Task breakdown',
                    'description' => 'Next actionable tasks with acceptance criteria',
                    'prompt' => 'Break the remaining work for "{name}" into concrete tasks with acceptance criteria and suggested order, based on the existing task board in the NAi context. Avoid duplicating tasks that already exist.',
                ],
                'risk-review' => [
                    'label' => 'Risk review',
                    'description' => 'Delivery risks and mitigations',
                    'prompt' => 'Review delivery risk for "{name}" using its status, priority, due date, and task distribution in the NAi context. Rank the risks, propose mitigations and owners, and identify what is blocked or under-specified.',
                ],
            ],
        ];
    }

    public static function for(string $subjectType): array
    {
        return self::all()[$subjectType] ?? [];
    }

    public static function get(string $subjectType, string $key): ?array
    {
        return self::for($subjectType)[$key] ?? null;
    }

    public static function render(string $subjectType, string $key, string $name): ?string
    {
        $playbook = self::get($subjectType, $key);

        return $playbook ? str_replace('{name}', $name, $playbook['prompt']) : null;
    }
}
