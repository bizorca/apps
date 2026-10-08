<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services;

/**
 * Seeded starter playbooks (FR-4.9).
 *
 * Deliberately generic. These ship the SHAPE of a quarterly operating rhythm
 * without shipping anyone's intellectual property — no EOS, Scaling Up,
 * StratOp, or Pinnacle terminology, no trademarked artefact names. A coach who
 * runs one of those methods fills in their own language; a coach who runs
 * their own process has somewhere to start.
 *
 * See SPEC.md §12.2 item 5: shipping generic shapes is the trademark posture
 * until decided otherwise.
 */
final class StarterPlaybooks
{
    /**
     * Install a starter playbook into a tenant and publish it.
     *
     * @return int The playbook id.
     */
    public static function installQuarterlyRhythm(int $tenantId, ?int $userId = null): int
    {
        $playbookId = PlaybookAuthor::create(
            $tenantId,
            '90-day operating rhythm',
            'A quarterly cycle: get oriented, agree the priorities, work them, review honestly.',
            $userId
        );

        $draft = PlaybookAuthor::draftVersion($tenantId, $playbookId);
        $versionId = (int) $draft['id'];

        $orient = PlaybookAuthor::addPhase($tenantId, $versionId, 'Get oriented', 'Understand the business before changing anything.');

        $kickoff = PlaybookAuthor::addStep($tenantId, $versionId, $orient, 'Kickoff conversation', [
            'coach_guidance'    => "Ninety minutes. Listen more than you talk.\n\nWhat you are after: how they make money, what is actually keeping them up at night, and who really decides things. Resist solving anything today — the first session is for mapping the territory, and a fix offered now will be the wrong one.",
            'client_guidance'   => 'We will spend about ninety minutes getting oriented — how the business works, what is going well, and what you would change first if you could.',
            'estimated_minutes' => 90,
        ]);
        PlaybookAuthor::addArtifact($tenantId, $kickoff, 'session', 'Kickoff session', ['duration_minutes' => 90]);

        $numbers = PlaybookAuthor::addStep($tenantId, $versionId, $orient, 'Understand the numbers', [
            'coach_guidance'    => "Trailing twelve months of P&L, the current balance sheet, and whatever they use to track cash.\n\nIf the books are a mess, that IS the finding. Do not fix the bookkeeping yourself — note it and decide together whether it becomes a priority.",
            'client_guidance'   => 'We will need a few financial documents so we are working from real numbers rather than impressions.',
            'completion_rule'   => 'artifacts_complete',
        ]);
        PlaybookAuthor::addArtifact($tenantId, $numbers, 'document', 'Trailing 12-month P&L', ['requested_from' => 'client_owner']);
        PlaybookAuthor::addArtifact($tenantId, $numbers, 'document', 'Current balance sheet', ['requested_from' => 'client_owner']);

        PlaybookAuthor::addStep($tenantId, $versionId, $orient, 'Talk to the team', [
            'coach_guidance'  => 'Optional, and worth it. Thirty minutes each with two or three people who are not the owner. You will hear a different company.',
            'client_guidance' => 'With your blessing, short conversations with a few of your people.',
            'is_required'     => 0,
        ]);

        $agree = PlaybookAuthor::addPhase($tenantId, $versionId, 'Agree the quarter', 'Pick few enough things that they can actually get done.');

        $priorities = PlaybookAuthor::addStep($tenantId, $versionId, $agree, 'Set the quarter\'s priorities', [
            'coach_guidance'    => "Three to seven. Push back hard above seven — a list of twelve priorities is a list of none, and agreeing to it is the single most common way a quarter gets wasted.\n\nEach one needs an owner who is a person, a date inside the quarter, and a way to tell whether it happened.",
            'client_guidance'   => 'Together we will choose the three to seven things that matter most this quarter, and agree who owns each one.',
            'estimated_minutes' => 120,
        ]);
        PlaybookAuthor::addArtifact($tenantId, $priorities, 'goal', 'Quarterly priorities', ['count_hint' => '3-7']);

        $scoreboard = PlaybookAuthor::addStep($tenantId, $versionId, $agree, 'Build the scoreboard', [
            'coach_guidance'  => "Five to fifteen numbers, reviewed weekly. Fewer is better.\n\nEach number needs a person responsible for entering it. A metric nobody owns stops being updated by week three.",
            'client_guidance' => 'A short list of numbers we will look at every week, so progress is visible rather than remembered.',
        ]);
        PlaybookAuthor::addArtifact($tenantId, $scoreboard, 'metric', 'Weekly scoreboard', ['frequency' => 'weekly']);

        $work = PlaybookAuthor::addPhase($tenantId, $versionId, 'Work the quarter', 'The part where the rhythm does the work.');

        PlaybookAuthor::addStep($tenantId, $versionId, $work, 'Regular working sessions', [
            'coach_guidance'  => "Same day, same time, same agenda. The consistency is the intervention.\n\nOpen every session with the commitments from last time. Not as an inquisition — as the thing that makes commitments mean something.",
            'client_guidance' => 'We meet on a set rhythm. Every session starts by reviewing what we each said we would do.',
            'gating'          => 'parallel',
        ]);

        PlaybookAuthor::addStep($tenantId, $versionId, $work, 'Mid-quarter honesty check', [
            'coach_guidance'  => "Around week six or seven. Which priorities are actually going to land?\n\nKilling one now is a better outcome than discovering in week twelve that four were theatre. Say so plainly.",
            'client_guidance' => 'Halfway through, an honest look at what will and will not get done — with time left to change course.',
            'gating'          => 'triggered',
            'gate_config'     => ['type' => 'date', 'offset_days' => 45],
        ]);

        $close = PlaybookAuthor::addPhase($tenantId, $versionId, 'Close the quarter', 'Score it honestly, then set up the next one.');

        $review = PlaybookAuthor::addStep($tenantId, $versionId, $close, 'Score the quarter', [
            'coach_guidance'  => "Score each priority done or not done. Resist partial credit — it is how a business talks itself into a year of almost.\n\nThen the real question, which is not what got done but what got in the way.",
            'client_guidance' => 'We score the quarter honestly, then work out what to carry forward.',
            'gating'          => 'triggered',
            'gate_config'     => ['type' => 'date', 'offset_days' => 84],
        ]);
        PlaybookAuthor::addArtifact($tenantId, $review, 'session', 'Quarterly review', ['duration_minutes' => 180]);

        PlaybookAuthor::addStep($tenantId, $versionId, $close, 'Set up the next quarter', [
            'coach_guidance'  => 'Roll forward what still matters, close what does not, and reset the priorities. Then start again.',
            'client_guidance' => 'We reset for the next ninety days.',
        ]);

        PlaybookAuthor::publish($tenantId, $versionId, 'Seeded starter playbook');

        \Bizorca\Pilotage\Core\Database::conn()
            ->prepare('UPDATE pl_playbooks SET is_system = 1 WHERE tenant_id = :tid AND id = :id')
            ->execute(['tid' => $tenantId, 'id' => $playbookId]);

        return $playbookId;
    }
}
