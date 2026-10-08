<?php
// ============================================================
// CoopConvert — documents.php
// Washington state cooperative document templates
//
// IMPORTANT: These templates are provided for educational and
// planning purposes only. They are NOT legal advice and are NOT
// a substitute for review by a licensed Washington state attorney
// experienced in cooperative law. Every cooperative formation
// should be reviewed by qualified legal counsel before filing or
// executing any documents.
// ============================================================

/**
 * Returns available document types for a given cooperative structure.
 *
 * @param string $structure  One of the STRUCTURES keys
 * @return array  ['doc_type' => 'Display Label']
 */
function getAvailableDocuments(string $structure): array {
    $map = [
        'worker_coop_llc' => [
            'operating_agreement'  => 'Operating Agreement',
            'membership_agreement' => 'Membership Agreement',
        ],
        'worker_coop_corp' => [
            'articles_of_incorporation' => 'Articles of Incorporation',
            'bylaws'                    => 'Bylaws',
            'membership_agreement'      => 'Membership Agreement',
        ],
        'ulca_multistakeholder' => [
            'articles_of_organization' => 'Articles of Organization (ULCA)',
            'bylaws'                   => 'Bylaws',
            'membership_classes'       => 'Membership Class Definitions',
        ],
        'consumer_coop' => [
            'articles_of_incorporation' => 'Articles of Incorporation',
            'bylaws'                    => 'Bylaws',
        ],
        'esop' => [],
    ];
    return $map[$structure] ?? [];
}

/**
 * Render a document template as an HTML string.
 *
 * @param string $docType     e.g. 'operating_agreement'
 * @param array  $business    Row from the businesses table
 * @param array  $assessment  Row from structure_assessments (answers may be JSON string)
 * @return string  HTML
 */
function renderDocument(string $docType, array $business, array $assessment): string {
    // Decode answers if stored as JSON string
    if (isset($assessment['answers']) && is_string($assessment['answers'])) {
        $assessment['answers'] = json_decode($assessment['answers'], true) ?? [];
    }

    return match ($docType) {
        'operating_agreement'       => _renderOperatingAgreement($business, $assessment),
        'membership_agreement'      => _renderMembershipAgreement($business, $assessment),
        'articles_of_incorporation' => _renderArticlesOfIncorporation($business, $assessment),
        'bylaws'                    => _renderBylaws($business, $assessment),
        'articles_of_organization'  => _renderArticlesOfOrganization($business, $assessment),
        'membership_classes'        => _renderMembershipClasses($business, $assessment),
        default                     => '<p><em>Document type not found.</em></p>',
    };
}

// ── Shared disclaimer banner ─────────────────────────────────
function _disclaimer(): string {
    return '
    <div style="border: 2px solid #b91c1c; background: #fef2f2; padding: 1rem; margin-bottom: 2rem; border-radius: 4px;">
        <strong style="color: #b91c1c;">ATTORNEY REVIEW REQUIRED</strong>
        <p style="margin: 0.5rem 0 0;">This document template is provided for planning and educational purposes only. It is not legal advice and does not constitute a finished legal document. Washington cooperative law is complex — every conversion should be reviewed by a licensed attorney experienced in RCW 23.78, RCW 23.86, RCW 25.15, or RCW 23B as applicable before any documents are filed or signed. Placeholders in [BRACKETS] require completion with information specific to your situation.</p>
    </div>';
}

// ── Operating Agreement (Worker Co-op LLC) ───────────────────
function _renderOperatingAgreement(array $business, array $assessment): string {
    $name = htmlspecialchars($business['name'] ?? '[BUSINESS NAME]', ENT_QUOTES, 'UTF-8');
    return _disclaimer() . "
<article class='document'>
<h1>OPERATING AGREEMENT<br><small>{$name}, LLC</small></h1>
<p><em>A Worker Cooperative Limited Liability Company organized under RCW 25.15 (Washington Limited Liability Company Act)</em></p>

<h2>RECITALS</h2>
<p>This Operating Agreement (\"Agreement\") is entered into as of [DATE], by and among the Members listed on Exhibit A attached hereto and incorporated herein by this reference. The Members desire to form a worker cooperative limited liability company under the laws of the State of Washington for the purposes set forth herein, consistent with cooperative principles as recognized by the International Cooperative Alliance.</p>

<h2>ARTICLE I — FORMATION AND NAME</h2>
<p><strong>1.1 Name.</strong> The name of the limited liability company is <strong>{$name}, LLC</strong> (the \"Company\"). The Company may conduct business under such trade names or assumed names as the Members may from time to time determine.</p>
<p><strong>1.2 Registered Agent.</strong> The Company's registered agent in the State of Washington shall be [REGISTERED AGENT NAME], located at [REGISTERED AGENT ADDRESS]. The registered agent may be changed by resolution of the Members and filing of the appropriate form with the Washington Secretary of State.</p>
<p><strong>1.3 Principal Place of Business.</strong> The principal place of business of the Company shall be [STREET ADDRESS, CITY, STATE, ZIP]. The Company may maintain additional places of business as determined by the Members.</p>
<p><strong>1.4 Term.</strong> The Company shall have perpetual existence unless dissolved pursuant to Article IX of this Agreement or as required by law.</p>
<p><strong>1.5 Purpose.</strong> The purpose of the Company is to engage in [DESCRIBE BUSINESS ACTIVITIES], and any other lawful business activity approved by the Members. The Company shall operate according to cooperative principles, including democratic member control, economic participation by members, and concern for community.</p>

<h2>ARTICLE II — COOPERATIVE PRINCIPLES AND MEMBERSHIP</h2>
<p><strong>2.1 Cooperative Character.</strong> The Company is organized and shall operate as a worker cooperative. Membership is open to all persons who are employed by the Company and who satisfy the requirements set forth in this Article. The Company shall be democratically controlled by its worker-members, with each member having equal voting rights regardless of capital contribution, consistent with the principle of one member, one vote.</p>
<p><strong>2.2 Eligibility.</strong> Any person employed by the Company on a regular basis (defined as working at least [MINIMUM HOURS, e.g., 20] hours per week for at least [PROBATIONARY PERIOD, e.g., six (6)] consecutive months) is eligible to apply for membership. Membership is not automatic; it requires application, approval by existing Members, and payment of the membership fee described in Section 2.4.</p>
<p><strong>2.3 Application and Admission.</strong> To become a Member, an eligible employee shall submit a written membership application to the Board of Directors (or, if no Board exists, to the Members collectively). Admission requires approval by a [SUPERMAJORITY, e.g., two-thirds (2/3)] vote of existing Members. A rejected applicant may reapply after [WAITING PERIOD, e.g., ninety (90)] days.</p>
<p><strong>2.4 Membership Fee and Capital Account.</strong> Each Member shall contribute a membership fee of $[AMOUNT] upon admission (the \"Membership Fee\"). The Membership Fee shall be credited to the Member's internal capital account. Additional capital contributions may be required by vote of the Members. Members may not transfer or assign their membership interest; upon cessation of employment, the Member's interest is redeemed as provided in Article VI.</p>
<p><strong>2.5 Non-Member Workers.</strong> The Company may employ non-member workers. The ratio of non-member worker hours to total worker hours shall not exceed [PERCENTAGE, e.g., thirty percent (30%)] in any fiscal year without a vote of the Members to expand the membership eligibility period.</p>

<h2>ARTICLE III — GOVERNANCE AND VOTING</h2>
<p><strong>3.1 Democratic Control.</strong> Each Member shall have one (1) vote on all matters submitted to a vote of the Members, regardless of the size of the Member's capital account or length of membership. No Member may vote by proxy on matters requiring a vote of the Members at a duly noticed meeting.</p>
<p><strong>3.2 Annual Meeting.</strong> The Company shall hold an annual meeting of Members no later than [MONTH, e.g., March 31] of each year. The agenda shall include, at minimum: (a) review of the annual financial statements; (b) election of any Board positions or officers; (c) determination of patronage allocations for the prior fiscal year; and (d) such other business as properly comes before the meeting.</p>
<p><strong>3.3 Special Meetings.</strong> Special meetings of Members may be called by the Board of Directors, any officer, or by Members holding at least [PERCENTAGE, e.g., twenty percent (20%)] of total membership votes. Notice of any special meeting shall be given no less than [NOTICE PERIOD, e.g., ten (10)] days before the meeting, stating the time, place, and purpose.</p>
<p><strong>3.4 Quorum.</strong> A quorum for the transaction of business at any meeting of Members shall be [QUORUM PERCENTAGE, e.g., a majority] of the Members then in good standing. If a quorum is not present, the meeting may be adjourned.</p>
<p><strong>3.5 Board of Directors (Optional).</strong> The Members may elect a Board of Directors of [NUMBER, e.g., three (3) to five (5)] members to manage day-to-day affairs. Directors shall serve terms of [TERM, e.g., one (1) year] and may be removed by a majority vote of Members. If no Board is elected, the Members shall manage the Company collectively.</p>

<h2>ARTICLE IV — FINANCIAL MATTERS AND PATRONAGE</h2>
<p><strong>4.1 Fiscal Year.</strong> The fiscal year of the Company shall end on [DATE, e.g., December 31] of each year.</p>
<p><strong>4.2 Capital Accounts.</strong> The Company shall maintain an individual internal capital account for each Member, credited with: (a) the Member's membership fee; (b) any additional capital contributions; and (c) allocated net margins (patronage). Capital accounts shall be debited for any redemptions or allocated losses in accordance with this Agreement.</p>
<p><strong>4.3 Allocation of Net Margins.</strong> At the close of each fiscal year, the Company's net margin (surplus) shall be allocated among the Members in proportion to each Member's patronage — defined as hours worked during the fiscal year — relative to total member hours worked. Losses shall be allocated in the same manner, subject to the limits of each Member's capital account balance.</p>
<p><strong>4.4 Patronage Distributions.</strong> The Members shall determine at the annual meeting what portion of allocated net margins shall be: (a) distributed in cash (the \"cash portion\"); and (b) retained in the Company as a written notice of allocation credited to Member capital accounts (the \"retained portion\"). The cash portion shall constitute at least [PERCENTAGE, e.g., twenty percent (20%)] of the total allocation, consistent with Subchapter T of the Internal Revenue Code if the Company elects cooperative tax treatment.</p>
<p><strong>4.5 Redemption of Retained Allocations.</strong> Retained written notices of allocation shall be redeemed on a FIFO (first-in, first-out) basis as the Company's financial position permits, as determined by the Members. The Company shall endeavor to redeem retained allocations within [PERIOD, e.g., five (5) years] of issuance.</p>

<h2>ARTICLE V — OFFICERS</h2>
<p><strong>5.1 Officers.</strong> The Company shall have, at minimum, a President and a Secretary. The Members may create additional officer positions by vote. Officers shall be elected annually at the annual meeting and may be removed by a majority vote of Members at any time.</p>
<p><strong>5.2 Duties.</strong> The President shall preside at meetings, execute contracts on behalf of the Company (subject to Member approval for major contracts), and perform such duties as the Members may direct. The Secretary shall maintain minutes of all meetings, the membership register, and such other records as required by law or this Agreement.</p>

<h2>ARTICLE VI — MEMBER WITHDRAWAL AND REDEMPTION</h2>
<p><strong>6.1 Voluntary Withdrawal.</strong> A Member may withdraw from the Company upon [NOTICE PERIOD, e.g., sixty (60)] days written notice to the Secretary. Upon withdrawal, the Member shall cease to have any voting rights and shall be entitled to redemption of their capital account balance as provided in Section 6.3.</p>
<p><strong>6.2 Termination of Employment.</strong> Membership automatically terminates upon the Member's cessation of employment with the Company for any reason, including resignation, discharge, retirement, or death. The former Member (or their estate) shall be entitled to redemption of their capital account balance as provided in Section 6.3.</p>
<p><strong>6.3 Redemption Process.</strong> The Company shall redeem a departing Member's capital account balance within [PERIOD, e.g., two (2) years] of the effective date of termination, subject to the Company's financial capacity. Redemption shall be paid in cash or by promissory note bearing interest at [RATE, e.g., the applicable federal rate], at the Company's election. The membership fee component shall be redeemed within [PERIOD, e.g., six (6) months]. Retained written notices of allocation shall be redeemed on the same schedule as for continuing members unless the Board accelerates redemption.</p>

<h2>ARTICLE VII — TRANSFER RESTRICTIONS</h2>
<p><strong>7.1 No Transfer.</strong> No Member may sell, assign, pledge, hypothecate, or otherwise transfer or encumber all or any portion of their membership interest in the Company without the prior written approval of [SUPERMAJORITY, e.g., two-thirds (2/3)] of the remaining Members. Any purported transfer in violation of this Section shall be null and void. The Company shall not be required to recognize any transferee as a Member.</p>

<h2>ARTICLE VIII — AMENDMENTS</h2>
<p><strong>8.1 Amendment.</strong> This Agreement may be amended by a [SUPERMAJORITY, e.g., two-thirds (2/3)] vote of Members at any duly noticed meeting of Members, provided that written notice of the proposed amendment is provided to all Members no less than [NOTICE PERIOD, e.g., fourteen (14)] days before the meeting. No amendment shall divest any Member of a vested right in their capital account without the consent of that Member.</p>

<h2>ARTICLE IX — DISSOLUTION</h2>
<p><strong>9.1 Dissolution.</strong> The Company may be dissolved by a [SUPERMAJORITY, e.g., two-thirds (2/3)] vote of Members. Upon dissolution, the assets of the Company shall be applied in the following order: (a) payment of all Company debts and liabilities; (b) redemption of Member capital accounts in proportion to their balances; and (c) any remaining assets shall be distributed to [CHARITABLE ORGANIZATION OR COOPERATIVE DEVELOPMENT FUND, as determined by Members], consistent with the cooperative principle of concern for community and the non-distribution constraint applicable to cooperative surpluses.</p>

<h2>ARTICLE X — MISCELLANEOUS</h2>
<p><strong>10.1 Governing Law.</strong> This Agreement shall be governed by the laws of the State of Washington, including RCW 25.15 (Washington Limited Liability Company Act).</p>
<p><strong>10.2 Entire Agreement.</strong> This Agreement, together with the Exhibits hereto, constitutes the entire agreement among the Members with respect to the subject matter hereof and supersedes all prior agreements and understandings, whether written or oral.</p>
<p><strong>10.3 Severability.</strong> If any provision of this Agreement is held to be invalid or unenforceable, the remaining provisions shall continue in full force and effect.</p>

<h2>SIGNATURES</h2>
<p>IN WITNESS WHEREOF, the undersigned Members have executed this Operating Agreement as of the date first written above.</p>
<p>
[MEMBER NAME] _______________________________ Date: _____________<br>
[MEMBER NAME] _______________________________ Date: _____________<br>
[MEMBER NAME] _______________________________ Date: _____________<br>
<em>(Add signature lines for all initial members. Attach Exhibit A listing all initial members, their capital contributions, and membership fee amounts.)</em>
</p>
</article>";
}

// ── Membership Agreement ─────────────────────────────────────
function _renderMembershipAgreement(array $business, array $assessment): string {
    $name = htmlspecialchars($business['name'] ?? '[BUSINESS NAME]', ENT_QUOTES, 'UTF-8');
    return _disclaimer() . "
<article class='document'>
<h1>WORKER MEMBERSHIP AGREEMENT<br><small>{$name}</small></h1>
<p><em>This Membership Agreement (\"Agreement\") is entered into between {$name} (the \"Cooperative\") and the individual identified below (\"Applicant\"), as of [DATE].</em></p>

<h2>SECTION 1 — MEMBERSHIP APPLICATION AND COMMITMENT</h2>
<p><strong>1.1 Application.</strong> The Applicant hereby applies for worker membership in the Cooperative. The Applicant acknowledges that they have read and understand the Cooperative's Operating Agreement (or Bylaws), and agrees to be bound by all terms thereof, as amended from time to time.</p>
<p><strong>1.2 Probationary Period.</strong> Prior to formal membership approval, the Applicant will complete a probationary employment period of [DURATION, e.g., six (6) months]. During the probationary period, the Applicant is an employee of the Cooperative but is not yet a Member and does not have voting rights. At the conclusion of the probationary period, existing Members will vote on whether to admit the Applicant as a Member.</p>
<p><strong>1.3 Membership Fee.</strong> Upon admission as a Member, the Applicant agrees to pay the membership fee of $[AMOUNT]. This fee may be paid in a lump sum or through payroll deductions of $[AMOUNT] per [PERIOD] over [DURATION]. The membership fee is credited to the Member's internal capital account and is not a wage or compensation.</p>
<p><strong>1.4 Acknowledgment of Cooperative Principles.</strong> The Applicant understands that this Cooperative operates according to democratic cooperative principles: one member, one vote; surplus distributed according to labor contribution (patronage); open and voluntary membership; and concern for community. The Applicant agrees to participate actively in the governance of the Cooperative, including attending meetings and voting.</p>

<h2>SECTION 2 — RIGHTS OF MEMBERSHIP</h2>
<p><strong>2.1 Voting Rights.</strong> Upon admission as a Member in good standing, the Member shall have one (1) vote on all matters submitted to a vote of the Members, regardless of the size of the Member's capital account or seniority.</p>
<p><strong>2.2 Patronage Allocations.</strong> The Member shall be entitled to receive an allocation of the Cooperative's annual net margin (surplus) in proportion to the Member's hours worked during the fiscal year relative to total Member hours, as determined annually by the Members. A portion of this allocation may be distributed in cash; the remainder may be retained in the Cooperative as a written notice of allocation credited to the Member's capital account.</p>
<p><strong>2.3 Capital Account.</strong> The Cooperative shall maintain an internal capital account in the Member's name reflecting the Member's equity interest. The Member shall receive an annual statement of their capital account balance.</p>
<p><strong>2.4 Access to Records.</strong> The Member shall have the right, upon reasonable written notice, to inspect the Cooperative's financial records, meeting minutes, and membership list, subject to reasonable confidentiality protections.</p>

<h2>SECTION 3 — MEMBER OBLIGATIONS</h2>
<p><strong>3.1 Employment.</strong> Membership is contingent on continued employment with the Cooperative. If the Member's employment terminates for any reason, membership automatically terminates as well. The Member's capital account shall be redeemed as provided in the Operating Agreement.</p>
<p><strong>3.2 Participation.</strong> The Member agrees to participate in annual meetings and special meetings of the Cooperative. Active participation is a condition of good standing.</p>
<p><strong>3.3 Confidentiality.</strong> The Member agrees to keep confidential all non-public financial and business information of the Cooperative, both during and after the period of membership.</p>
<p><strong>3.4 Non-Competition.</strong> [OPTIONAL — CONSULT ATTORNEY] During the period of membership, the Member agrees not to engage, directly or indirectly, in any business that competes with the Cooperative within [GEOGRAPHIC AREA] without the prior written consent of the Members.</p>

<h2>SECTION 4 — WITHDRAWAL AND REDEMPTION</h2>
<p><strong>4.1 Voluntary Withdrawal.</strong> The Member may withdraw from the Cooperative upon [NOTICE PERIOD, e.g., sixty (60)] days written notice to the Secretary. Upon withdrawal, the Member's capital account balance shall be redeemed as provided in the Operating Agreement.</p>
<p><strong>4.2 Tax Acknowledgment.</strong> The Member acknowledges that patronage allocations, whether distributed in cash or retained as written notices of allocation, may constitute taxable income in the year of allocation or distribution, as determined under Subchapter T of the Internal Revenue Code. The Member is responsible for consulting with a tax advisor regarding the tax treatment of membership income. The Cooperative will provide the Member with a Form 1099-PATR or such other tax documentation as required by law.</p>

<h2>SECTION 5 — DISPUTE RESOLUTION</h2>
<p><strong>5.1 Internal Process.</strong> Any dispute between the Member and the Cooperative shall first be submitted to the Board of Directors (or Member committee) for resolution through a good-faith internal process. The Member shall have the right to present their case and be heard.</p>
<p><strong>5.2 Mediation.</strong> If the internal process does not resolve the dispute within [PERIOD, e.g., thirty (30)] days, either party may request non-binding mediation through [MEDIATION ORGANIZATION, e.g., Dispute Resolution Center of King County] or another mutually agreed mediator. Costs of mediation shall be shared equally.</p>
<p><strong>5.3 Arbitration.</strong> If mediation fails, disputes shall be resolved by binding arbitration under the rules of [ARBITRATION ORGANIZATION] in [CITY], Washington. The decision of the arbitrator shall be final and binding.</p>

<h2>SIGNATURES</h2>
<p>By signing below, the Applicant acknowledges that they have read and understood this Membership Agreement and agree to be bound by its terms.</p>
<p>
<strong>Applicant:</strong><br>
Name: [MEMBER_NAME]<br>
Signature: _______________________________ Date: _____________<br>
Address: [ADDRESS]<br>
Email: [EMAIL]<br>
Start Date: [EMPLOYMENT_START_DATE]
</p>
<p>
<strong>On behalf of {$name}:</strong><br>
Name: [AUTHORIZED OFFICER NAME]<br>
Title: [TITLE]<br>
Signature: _______________________________ Date: _____________
</p>
</article>";
}

// ── Articles of Incorporation (Worker Co-op Corp / Consumer Co-op) ──
function _renderArticlesOfIncorporation(array $business, array $assessment): string {
    $name      = htmlspecialchars($business['name'] ?? '[BUSINESS NAME]', ENT_QUOTES, 'UTF-8');
    $structure = $assessment['recommended_structure'] ?? 'worker_coop_corp';
    $statute   = ($structure === 'consumer_coop') ? 'RCW 23.86 (Cooperative Associations Act)' : 'RCW 23B (Washington Business Corporation Act)';
    $type      = ($structure === 'consumer_coop') ? 'consumer cooperative association' : 'worker cooperative corporation';
    return _disclaimer() . "
<article class='document'>
<h1>ARTICLES OF INCORPORATION<br><small>{$name}</small></h1>
<p><em>Filed with the Washington Secretary of State pursuant to {$statute}</em></p>

<h2>ARTICLE I — NAME</h2>
<p>The name of this corporation is <strong>{$name}</strong>.</p>

<h2>ARTICLE II — PURPOSE AND COOPERATIVE CHARACTER</h2>
<p>This corporation is organized as a {$type}. Its primary purpose is to [DESCRIBE PRIMARY BUSINESS ACTIVITIES]. In conducting its business, the corporation shall be guided by cooperative principles, including democratic member control, equitable distribution of economic benefits according to member participation, and concern for the communities in which it operates.</p>
<p>The corporation shall have all powers granted to corporations organized under {$statute}, as amended, and all powers necessary or convenient to carry out its purposes, including but not limited to: entering into contracts; acquiring, holding, and disposing of real and personal property; borrowing money; and employing agents and workers.</p>

<h2>ARTICLE III — AUTHORIZED SHARES / MEMBERSHIP INTERESTS</h2>
" . (($structure === 'consumer_coop') ? "
<p>This association shall have one (1) class of membership. Each member shall hold one (1) membership interest. Membership interests are not transferable except as provided in the Bylaws. The membership fee shall be $[AMOUNT] per member, as set by the Board of Directors.</p>
" : "
<p>The total number of shares of stock the corporation is authorized to issue is [TOTAL SHARES, e.g., 1,000,000] shares. The authorized shares shall consist of:</p>
<ul>
    <li><strong>Class A Worker Member Shares:</strong> [NUMBER] shares, $[PAR VALUE] par value per share. Class A shares are available only to persons employed by the corporation who have been admitted as worker-members pursuant to the Bylaws. Each Class A share carries one (1) vote. Class A shares are not freely transferable and may not be held by non-employees.</li>
    <li><strong>Class B Preferred Shares (Non-Voting):</strong> [NUMBER] shares, $[PAR VALUE] par value per share. [OPTIONAL — DELETE IF NOT NEEDED] Class B shares may be issued to investors or former worker-members. Class B shares carry no voting rights and are entitled to a cumulative preferred return of [PERCENTAGE, e.g., 8%] per year before any distribution to Class A shareholders.</li>
</ul>
<p>No person or entity may hold more than one (1) Class A Worker Member Share. The issuance, redemption, and transfer restrictions applicable to all share classes shall be set forth in the Bylaws.</p>
") . "
<h2>ARTICLE IV — REGISTERED AGENT</h2>
<p>The name of the registered agent is [REGISTERED AGENT NAME], and the address of the registered agent and the registered office is [STREET ADDRESS, CITY, WA ZIP]. The registered agent is authorized to accept service of process on behalf of the corporation.</p>

<h2>ARTICLE V — BOARD OF DIRECTORS</h2>
<p>The number of directors constituting the initial Board of Directors is [NUMBER, e.g., three (3)]. The names and addresses of the initial directors are:</p>
<ol>
    <li>[DIRECTOR 1 NAME], [ADDRESS]</li>
    <li>[DIRECTOR 2 NAME], [ADDRESS]</li>
    <li>[DIRECTOR 3 NAME], [ADDRESS]</li>
</ol>
<p>Directors shall be elected by the members at each annual meeting. No person who is not a member of the corporation (or, in the case of a worker cooperative, an employee of the corporation) may serve as a director, except that the Bylaws may permit a limited number of outside directors not to exceed [PERCENTAGE, e.g., twenty percent (20%)] of the total Board, for purposes of community representation or expertise.</p>

<h2>ARTICLE VI — INCORPORATORS</h2>
<p>The name and address of each incorporator is:</p>
<ol>
    <li>[INCORPORATOR NAME], [ADDRESS]</li>
</ol>

<h2>ARTICLE VII — LIABILITY AND INDEMNIFICATION</h2>
<p>No director of this corporation shall be personally liable to the corporation or its members for monetary damages for conduct as a director, except as otherwise required by RCW 23B.08.320 or applicable law. The corporation shall indemnify its directors, officers, and agents to the fullest extent permitted by Washington law, as set forth in the Bylaws.</p>

<h2>ARTICLE VIII — COOPERATIVE DISTRIBUTION CONSTRAINT</h2>
<p>This corporation shall operate on a cooperative basis for the mutual benefit of its members. Net margins (surpluses) shall be distributed to members in proportion to their patronage — defined as participation in the business of the corporation as set forth in the Bylaws — and not in proportion to capital ownership. The corporation shall not be operated for profit as its primary purpose. Any dissolution of the corporation shall be carried out consistent with Article [X] of the Bylaws, with remaining assets distributed to [COOPERATIVE DEVELOPMENT FUND OR CHARITABLE ORGANIZATION] to the extent permitted by law.</p>

<h2>ARTICLE IX — AMENDMENT</h2>
<p>These Articles of Incorporation may be amended in the manner provided by {$statute} and as further set forth in the Bylaws, provided that any amendment must be approved by a [SUPERMAJORITY, e.g., two-thirds (2/3)] vote of the members entitled to vote thereon.</p>

<h2>CERTIFICATION</h2>
<p>I, the undersigned, being the incorporator of {$name}, do hereby declare that the facts stated in these Articles of Incorporation are true.</p>
<p>
[INCORPORATOR NAME]: _______________________________ Date: _____________<br>
[ADDRESS]
</p>
<p><em>File this document with the Washington Secretary of State, Corporations Division, along with the applicable filing fee ($180 as of 2024 for most corporate filings). Retain a certified copy for the corporate records book.</em></p>
</article>";
}

// ── Bylaws (Worker Co-op Corp / Consumer Co-op) ──────────────
function _renderBylaws(array $business, array $assessment): string {
    $name      = htmlspecialchars($business['name'] ?? '[BUSINESS NAME]', ENT_QUOTES, 'UTF-8');
    $structure = $assessment['recommended_structure'] ?? 'worker_coop_corp';
    $isConsumer = ($structure === 'consumer_coop');
    $memberType = $isConsumer ? 'consumer member' : 'worker-member';
    $statute    = $isConsumer ? 'RCW 23.86' : 'RCW 23B';
    return _disclaimer() . "
<article class='document'>
<h1>BYLAWS OF {$name}</h1>
<p><em>Adopted by the Board of Directors and ratified by the founding members on [DATE], pursuant to {$statute}.</em></p>

<h2>ARTICLE I — OFFICES</h2>
<p><strong>1.1 Principal Office.</strong> The principal office of the corporation shall be located at [ADDRESS, CITY, WA ZIP]. The Board of Directors may change the principal office by resolution.</p>
<p><strong>1.2 Other Offices.</strong> The corporation may have other offices within or outside Washington as the Board may from time to time determine.</p>

<h2>ARTICLE II — MEMBERSHIP</h2>
<p><strong>2.1 Classes of Membership.</strong> The corporation shall have one (1) class of {$memberType}s." . (!$isConsumer ? " [If preferred/investor shares are authorized, add: The corporation shall also have Class B preferred shareholders as set forth in the Articles of Incorporation, who shall not be voting members.]" : "") . "</p>
<p><strong>2.2 Eligibility for Membership.</strong> " . ($isConsumer
    ? "Any individual or household residing within [SERVICE AREA] who pays the membership fee set forth in Section 2.4 and agrees to abide by these Bylaws is eligible to become a member."
    : "Any individual who has been employed by the corporation for at least [PROBATIONARY PERIOD, e.g., six (6)] months and who meets the qualifications established by the Board is eligible to apply for {$memberType} status.") . "</p>
<p><strong>2.3 Admission.</strong> Membership applications shall be reviewed by the Board of Directors. Admission requires a [MAJORITY/SUPERMAJORITY] vote of the Board. The Board shall notify the applicant of its decision within [DAYS] of receipt of a complete application.</p>
<p><strong>2.4 Membership Fee.</strong> The initial membership fee is $[AMOUNT], payable upon admission. The Board may adjust the membership fee by resolution, provided that no fee increase shall apply retroactively to existing members. The membership fee is refundable only as provided in Article VI (Withdrawal and Redemption).</p>
<p><strong>2.5 Good Standing.</strong> A member is in good standing if they have paid all required fees, are not subject to suspension, and are current on any payment plans for capital contributions. Only members in good standing may vote.</p>
<p><strong>2.6 Member Register.</strong> The Secretary shall maintain an accurate and current register of all members, including name, mailing address, email address, date of admission, and capital account balance. The register shall be available for inspection by any member upon reasonable notice.</p>

<h2>ARTICLE III — MEETINGS OF MEMBERS</h2>
<p><strong>3.1 Annual Meeting.</strong> An annual meeting of members shall be held each year on a date determined by the Board, no later than [MONTH/DAY]. At the annual meeting, the members shall: elect directors; receive and consider the annual financial report; approve the distribution of net margins; and transact such other business as may properly come before the meeting.</p>
<p><strong>3.2 Special Meetings.</strong> Special meetings of members may be called by: (a) the Board of Directors; (b) the President; or (c) members representing at least [PERCENTAGE, e.g., 20%] of voting membership. The call for a special meeting shall state the purpose(s), and only business within the stated purpose(s) may be conducted.</p>
<p><strong>3.3 Notice.</strong> Written notice of all meetings shall be provided to each member of record not less than [DAYS, e.g., 10] nor more than [DAYS, e.g., 60] days before the meeting. Notice may be delivered by mail, email, or personal delivery. Notice shall state the date, time, location, and, for special meetings, the purpose.</p>
<p><strong>3.4 Quorum.</strong> [PERCENTAGE, e.g., A majority] of the members in good standing, present in person or by permitted alternative means, shall constitute a quorum. If a quorum is not present, the meeting shall be adjourned until a quorum can be obtained.</p>
<p><strong>3.5 Voting.</strong> Each member in good standing shall have one (1) vote. Cumulative voting is not permitted. Members may not vote by proxy except on matters where the Board has expressly permitted proxy voting by resolution.</p>
<p><strong>3.6 Remote Participation.</strong> The Board may authorize members to participate in meetings by telephone, video conference, or similar means, provided all participants can hear and be heard simultaneously. Participation by such means constitutes presence at the meeting.</p>

<h2>ARTICLE IV — BOARD OF DIRECTORS</h2>
<p><strong>4.1 Powers.</strong> The Board of Directors shall manage the business and affairs of the corporation, subject to the direction of the members on matters reserved to member vote by these Bylaws or applicable law.</p>
<p><strong>4.2 Number and Qualifications.</strong> The Board shall consist of [NUMBER, e.g., 3–7] directors. " . (!$isConsumer ? "All directors must be worker-members of the corporation in good standing, except that up to [NUMBER, e.g., one (1)] outside director(s) may be elected by the members to provide expertise or community representation." : "Directors shall be members in good standing. Up to [NUMBER] outside directors may be permitted.") . "</p>
<p><strong>4.3 Election and Term.</strong> Directors shall be elected by the members at each annual meeting. Each director shall serve a term of [TERM, e.g., one (1) year] and may serve up to [NUMBER, e.g., three (3)] consecutive terms, after which a [GAP, e.g., one-year] break is required before re-election.</p>
<p><strong>4.4 Removal.</strong> Any director may be removed, with or without cause, by a [SUPERMAJORITY, e.g., two-thirds (2/3)] vote of the members at any meeting at which a quorum is present, provided that notice of the removal vote was included in the meeting notice.</p>
<p><strong>4.5 Board Meetings.</strong> The Board shall meet at least [FREQUENCY, e.g., quarterly]. Special meetings of the Board may be called by the President or any two (2) directors. Notice of Board meetings shall be provided at least [DAYS, e.g., three (3)] days in advance, unless waived. A majority of directors shall constitute a quorum.</p>
<p><strong>4.6 Compensation.</strong> Directors shall serve without cash compensation unless the members vote otherwise. Directors shall be reimbursed for reasonable expenses incurred in the performance of their duties.</p>

<h2>ARTICLE V — OFFICERS</h2>
<p><strong>5.1 Officers.</strong> The officers of the corporation shall be a President, Secretary, and Treasurer, and such other officers as the Board may from time to time designate. Officers shall be elected by the Board at its first meeting following the annual meeting of members.</p>
<p><strong>5.2 President.</strong> The President shall be the chief executive officer of the corporation; shall preside at meetings of the members and the Board; shall execute contracts and instruments on behalf of the corporation as authorized by the Board; and shall perform such other duties as the Board may direct.</p>
<p><strong>5.3 Secretary.</strong> The Secretary shall keep minutes of all meetings of members and the Board; maintain the member register; give notice of meetings as required; maintain the corporate records; and perform such other duties as the Board may direct.</p>
<p><strong>5.4 Treasurer.</strong> The Treasurer shall oversee the financial affairs of the corporation; maintain accurate financial records and internal capital account records; prepare or oversee preparation of annual financial statements; and perform such other duties as the Board may direct.</p>

<h2>ARTICLE VI — PATRONAGE AND SURPLUS DISTRIBUTION</h2>
<p><strong>6.1 Cooperative Basis.</strong> This corporation operates on a cooperative basis. Net margins (operating surpluses after expenses and reserves) shall be distributed to members in proportion to their patronage with the corporation during the fiscal year, not in proportion to capital contributions.</p>
<p><strong>6.2 Patronage Defined.</strong> " . ($isConsumer
    ? "Patronage is defined as the dollar value of purchases made by each member from the corporation during the fiscal year."
    : "Patronage is defined as the hours worked by each worker-member during the fiscal year, as documented by the corporation's payroll records.") . "</p>
<p><strong>6.3 Allocation of Net Margins.</strong> Within [DAYS, e.g., ninety (90)] days after the close of each fiscal year, the Board shall determine the net margin available for allocation and distribute written notices of allocation to each member reflecting their proportionate share. At least [PERCENTAGE, e.g., 20%] of each member's allocation shall be distributed in cash within [DAYS, e.g., 30] days of the written notice, consistent with Subchapter T of the Internal Revenue Code.</p>
<p><strong>6.4 Reserves.</strong> Before allocating net margins to members, the Board may set aside reasonable reserves for working capital, capital improvements, and contingencies. The Board shall not retain reserves in excess of what is reasonably necessary for the corporation's needs.</p>
<p><strong>6.5 Redemption of Retained Allocations.</strong> Retained written notices of allocation shall be redeemed on a FIFO basis, as the corporation's cash position permits. The Board shall maintain a redemption schedule and endeavor to redeem retained allocations within [PERIOD, e.g., five (5)] years of issuance.</p>

<h2>ARTICLE VII — MEMBER WITHDRAWAL AND TERMINATION</h2>
<p><strong>7.1 Voluntary Withdrawal.</strong> A member may withdraw from the corporation by submitting written notice to the Secretary. Withdrawal shall become effective [DAYS, e.g., 30] days after receipt of notice. Upon withdrawal, the member shall forfeit voting rights and shall be entitled to redemption of their capital account balance as provided in Section 7.3.</p>
<p><strong>7.2 Involuntary Termination.</strong> The Board may terminate a member's membership for cause — including but not limited to: material violation of these Bylaws; conduct harmful to the corporation; or failure to pay required fees after written notice — following a fair hearing process in which the member has an opportunity to be heard. Termination requires a [SUPERMAJORITY, e.g., two-thirds (2/3)] vote of the Board.</p>
<p><strong>7.3 Redemption of Capital Account.</strong> Upon voluntary or involuntary termination of membership, the corporation shall redeem the member's capital account balance (including the membership fee) within [PERIOD, e.g., two (2) years] of the effective date of termination, subject to the corporation's financial capacity. Payment may be made in cash or by interest-bearing promissory note at the Board's election. Retained written notices of allocation shall be redeemed on the same schedule as for continuing members unless accelerated by the Board.</p>

<h2>ARTICLE VIII — INDEMNIFICATION</h2>
<p><strong>8.1</strong> The corporation shall indemnify any director, officer, employee, or agent of the corporation who is made a party to any proceeding by reason of their service to the corporation, to the fullest extent permitted by RCW 23B.08.510 through 23B.08.600 or RCW 23.86 as applicable, and as limited by any applicable insurance policy.</p>

<h2>ARTICLE IX — FISCAL YEAR AND RECORDS</h2>
<p><strong>9.1 Fiscal Year.</strong> The fiscal year of the corporation shall begin on [DATE] and end on [DATE, e.g., December 31] of each year.</p>
<p><strong>9.2 Financial Records.</strong> The corporation shall maintain complete and accurate financial records, including income statements, balance sheets, and capital account statements for each member. Financial statements shall be prepared annually and made available to all members within [DAYS, e.g., 90] days after fiscal year end.</p>
<p><strong>9.3 Member Inspection Rights.</strong> Any member may, upon written notice of at least [DAYS, e.g., five (5)] business days, inspect the corporation's financial records, bylaws, meeting minutes, and member register during normal business hours, subject to reasonable confidentiality limitations determined by the Board.</p>

<h2>ARTICLE X — DISSOLUTION</h2>
<p><strong>10.1</strong> The corporation may be dissolved by a [SUPERMAJORITY, e.g., two-thirds (2/3)] vote of the members at a meeting duly called for that purpose. Upon dissolution, after payment of all debts and liabilities, remaining assets shall be distributed: first, to redeem member capital accounts in proportion to their balances; then, any remainder to [COOPERATIVE DEVELOPMENT FUND OR CHARITABLE ORGANIZATION CONSISTENT WITH COOPERATIVE VALUES].</p>

<h2>ARTICLE XI — AMENDMENTS</h2>
<p><strong>11.1</strong> These Bylaws may be amended by a [SUPERMAJORITY, e.g., two-thirds (2/3)] vote of the members at any annual or special meeting, provided that the text of the proposed amendment was included in the meeting notice sent not less than [DAYS, e.g., 14] days before the meeting.</p>

<h2>CERTIFICATION</h2>
<p>These Bylaws were adopted by the Board of Directors of {$name} on [DATE] and ratified by the founding members on [DATE].</p>
<p>
President: _______________________________ Date: _____________<br>
Secretary: _______________________________ Date: _____________
</p>
</article>";
}

// ── Articles of Organization (ULCA Multi-Stakeholder) ────────
function _renderArticlesOfOrganization(array $business, array $assessment): string {
    $name = htmlspecialchars($business['name'] ?? '[BUSINESS NAME]', ENT_QUOTES, 'UTF-8');
    return _disclaimer() . "
<article class='document'>
<h1>ARTICLES OF ORGANIZATION<br><small>{$name}, a Limited Cooperative Association</small></h1>
<p><em>Filed with the Washington Secretary of State pursuant to RCW 23.78 (Uniform Limited Cooperative Association Act)</em></p>
<p><em>Note: The Uniform Limited Cooperative Association Act (ULCA), codified at RCW 23.78, is Washington's most flexible cooperative statute. It expressly permits multiple classes of members — including investor members — and provides robust statutory protections for the cooperative character of the organization. An attorney familiar with RCW 23.78 is strongly recommended for ULCA formations.</em></p>

<h2>ARTICLE I — NAME</h2>
<p>The name of this limited cooperative association is <strong>{$name}</strong>. The name shall include \"limited cooperative association,\" \"LCA,\" or \"co-op\" as required by RCW 23.78.130.</p>

<h2>ARTICLE II — PURPOSE</h2>
<p>This association is organized as a multi-stakeholder limited cooperative association pursuant to RCW 23.78. Its primary purpose is to [DESCRIBE BUSINESS ACTIVITIES], operated for the mutual benefit of its members according to cooperative principles. The association shall be conducted on a cooperative basis, with democratic member control, equitable distribution of economic benefits according to participation, and commitment to the communities it serves.</p>

<h2>ARTICLE III — REGISTERED AGENT AND OFFICE</h2>
<p>The name of the initial registered agent is [REGISTERED AGENT NAME]. The street address of the registered office, which is the same as the registered agent's business address, is [STREET ADDRESS, CITY, WA ZIP].</p>

<h2>ARTICLE IV — MEMBERSHIP CLASSES</h2>
<p>Pursuant to RCW 23.78.190, this association shall have the following classes of members:</p>
<ol>
    <li><strong>Class A — Worker Members:</strong> Individuals employed by the association who have been admitted as worker members pursuant to the Bylaws. Class A members have full voting rights (one member, one vote) and participate in patronage allocations based on labor contribution. Class A membership requires payment of the worker membership fee of $[AMOUNT].</li>
    <li><strong>Class B — Investor Members:</strong> Individuals, organizations, or entities that have contributed capital to the association pursuant to the Bylaws and have been admitted as investor members. Investor members have limited voting rights as set forth in the Bylaws; specifically, investor members as a class shall not hold more than [PERCENTAGE, e.g., 49%] of total voting power at any meeting of members, consistent with RCW 23.78.190(3). Investor members are entitled to a preferred economic return of [PERCENTAGE, e.g., 8%] per year on their contributed capital before any patronage distribution to Class A members.</li>
    <li><strong>Class C — Community Members (Optional):</strong> [DELETE IF NOT APPLICABLE] Individuals or organizations admitted as community members for the purpose of representing community interests. Community members may have advisory voting rights on specified matters as set forth in the Bylaws. Community members pay a membership fee of $[AMOUNT].</li>
</ol>
<p>The rights, duties, obligations, and preferences of each membership class shall be set forth in detail in the Bylaws. Pursuant to RCW 23.78.190, patron members (Classes A and C) shall at all times retain majority voting control.</p>

<h2>ARTICLE V — ORGANIZERS</h2>
<p>The names and addresses of the organizers of this association are:</p>
<ol>
    <li>[ORGANIZER NAME], [ADDRESS]</li>
    <li>[ORGANIZER NAME], [ADDRESS]</li>
</ol>

<h2>ARTICLE VI — INITIAL BOARD OF DIRECTORS</h2>
<p>The number of directors constituting the initial Board of Directors is [NUMBER]. Pursuant to RCW 23.78.350, the Bylaws shall specify the composition of the Board with respect to membership class representation. The names and addresses of the initial directors are:</p>
<ol>
    <li>[DIRECTOR NAME] (Worker Member Class), [ADDRESS]</li>
    <li>[DIRECTOR NAME] (Worker Member Class), [ADDRESS]</li>
    <li>[DIRECTOR NAME] (Investor Member Class), [ADDRESS]</li>
    <li>[DIRECTOR NAME] (At-large / Community), [ADDRESS]</li>
</ol>

<h2>ARTICLE VII — COOPERATIVE DISTRIBUTION CONSTRAINT</h2>
<p>Consistent with RCW 23.78.010 and the cooperative character of this association, net margins shall be allocated to patron members (Class A Worker Members and Class C Community Members) in proportion to their patronage with the association. Investor member (Class B) returns shall be limited to the preferred economic return specified in Article IV and the Bylaws, and shall not exceed a reasonable return on their contributed capital. This association shall not be operated for the primary purpose of returning profit to investor members.</p>

<h2>ARTICLE VIII — TERM</h2>
<p>The duration of this association shall be perpetual, unless dissolved pursuant to RCW 23.78 and the Bylaws.</p>

<h2>ARTICLE IX — LIABILITY</h2>
<p>No member of this limited cooperative association shall be personally liable for any debt, obligation, or liability of the association solely by reason of being a member, consistent with RCW 23.78.115.</p>

<h2>CERTIFICATION</h2>
<p>The undersigned organizer(s) declare that the statements made in these Articles of Organization are true and correct, and that this document is executed pursuant to RCW 23.78.130.</p>
<p>
[ORGANIZER NAME]: _______________________________ Date: _____________<br>
[ORGANIZER NAME]: _______________________________ Date: _____________
</p>
<p><em>File with the Washington Secretary of State with the applicable filing fee. Retain confirmed copies. Proceed to adopt Bylaws and Membership Class Definitions as companion documents.</em></p>
</article>";
}

// ── Membership Class Definitions (ULCA Multi-Stakeholder) ────
function _renderMembershipClasses(array $business, array $assessment): string {
    $name = htmlspecialchars($business['name'] ?? '[BUSINESS NAME]', ENT_QUOTES, 'UTF-8');
    return _disclaimer() . "
<article class='document'>
<h1>MEMBERSHIP CLASS DEFINITIONS AND RIGHTS SCHEDULE<br><small>{$name}, a Limited Cooperative Association</small></h1>
<p><em>This document is a companion to the Articles of Organization and Bylaws of {$name}. It sets forth in detail the rights, duties, obligations, and economic participation of each membership class pursuant to RCW 23.78.190. This document should be incorporated by reference into the Bylaws.</em></p>

<h2>BACKGROUND AND PURPOSE</h2>
<p>RCW 23.78 — the Uniform Limited Cooperative Association Act — allows Washington cooperatives to define multiple classes of membership with distinct voting rights, economic rights, and governance roles. This flexibility makes the ULCA ideal for multi-stakeholder cooperative models. However, it requires careful drafting: every membership class must have clearly defined rights, and the statute requires that patron members (those who participate in the cooperative's primary business) retain majority voting control at all times.</p>
<p>The following class definitions establish the framework for {$name}'s multi-stakeholder structure. These definitions shall govern until amended by the process specified in the Bylaws.</p>

<h2>CLASS A — WORKER MEMBERS</h2>
<p><strong>Definition.</strong> Class A Worker Members are individuals employed by {$name} on a regular and substantial basis who have completed the probationary period and been admitted to membership by the Board of Directors.</p>
<p><strong>Probationary Period.</strong> Prospective worker members shall complete an employment probationary period of [DURATION, e.g., six (6) months], during which they are employees but not voting members. At the conclusion of the probationary period, the Board shall vote on admission. Admission requires a [MAJORITY/SUPERMAJORITY] vote.</p>
<p><strong>Membership Fee.</strong> Each Class A member shall contribute a membership fee of $[AMOUNT] upon admission, credited to their internal capital account. This fee may be paid through payroll deductions over [PERIOD].</p>
<p><strong>Voting Rights.</strong> Each Class A member shall have one (1) vote on all matters submitted to member vote. Class A members elect [NUMBER OR PERCENTAGE, e.g., a majority] of the Board seats designated for worker member representation.</p>
<p><strong>Patronage Participation.</strong> Class A members participate in patronage allocations based on hours worked during each fiscal year, relative to total Class A member hours. Their share of patronage is calculated after payment of the Class B preferred return and funding of any required reserves.</p>
<p><strong>Capital Account.</strong> Each Class A member shall have an individual internal capital account reflecting: (a) membership fee; (b) additional capital contributions; and (c) cumulative patronage allocations retained in the association. Capital accounts shall be redeemed upon termination of membership as specified in the Bylaws.</p>
<p><strong>Termination of Membership.</strong> Class A membership automatically terminates upon cessation of employment. Membership may also be terminated for cause by a supermajority Board vote following a fair hearing. Upon termination, the former member's capital account balance shall be redeemed within [PERIOD].</p>

<h2>CLASS B — INVESTOR MEMBERS</h2>
<p><strong>Definition.</strong> Class B Investor Members are individuals, community development financial institutions, foundations, or other entities that have contributed investment capital to {$name} and been admitted by the Board of Directors.</p>
<p><strong>Admission.</strong> Class B membership requires: (a) a minimum capital contribution of $[MINIMUM, e.g., $1,000]; (b) execution of a Class B Membership Agreement; and (c) approval by the Board of Directors. There is no employment or residency requirement for Class B membership.</p>
<p><strong>Voting Rights.</strong> Class B members, voting as a class, shall have voting rights on the following specified matters only, as provided in RCW 23.78.190: (a) approval of any merger, conversion, or dissolution of the association; (b) approval of any amendment to the Articles of Organization or Bylaws that materially and adversely affects the rights of Class B members; and (c) election of [NUMBER] director seat(s) designated for investor member representation. On all other matters, Class B members are non-voting. The total voting power of Class B members, when voting alongside Class A members, shall not exceed [PERCENTAGE, e.g., 49%] of combined votes, consistent with RCW 23.78.190(3).</p>
<p><strong>Economic Rights.</strong> Class B members are entitled to a cumulative preferred return of [PERCENTAGE, e.g., 8%] per year on their contributed capital (the \"Preferred Return\"). The Preferred Return shall be paid before any patronage distributions to Class A members. If in any fiscal year the association has insufficient net margin to pay the full Preferred Return, the unpaid portion shall accumulate and must be paid in subsequent years before any Class A patronage distribution. Class B members do not participate in patronage distributions beyond the Preferred Return.</p>
<p><strong>Redemption.</strong> Class B contributed capital shall be redeemed on the schedule set forth in each member's Class B Membership Agreement, typically [TERM, e.g., 5–7 years]. The association may redeem Class B capital early with [NOTICE PERIOD] notice. Class B members may request redemption after [MINIMUM HOLD PERIOD], subject to the association's financial capacity and Board approval.</p>
<p><strong>Transfer.</strong> Class B membership interests may be transferred with Board approval. Any transferee must be admitted as a Class B member before the transfer is effective. The association shall have a right of first refusal on any proposed transfer at the same price and terms offered to a third party.</p>

<h2>CLASS C — COMMUNITY MEMBERS (IF APPLICABLE)</h2>
<p><em>[Complete this section only if Community membership is included in the Articles of Organization. Delete otherwise.]</em></p>
<p><strong>Definition.</strong> Class C Community Members are individuals or organizations admitted to represent community interests in the governance of {$name}. Class C membership is open to [DEFINE ELIGIBILITY, e.g., residents of the neighborhood served by the association; customers of the business; members of partner organizations].</p>
<p><strong>Admission.</strong> Class C membership requires: (a) payment of the community membership fee of $[AMOUNT]; and (b) approval by the Board of Directors or, at the Board's option, by a vote of existing Class C members.</p>
<p><strong>Voting Rights.</strong> Each Class C member shall have one (1) advisory vote on matters designated by the Bylaws as subject to Class C input. Class C members elect [NUMBER] director seat(s) on the Board designated for community representation. On operational matters reserved to Class A worker members, Class C members do not vote.</p>
<p><strong>Economic Rights.</strong> Class C members are entitled to [DESCRIBE, e.g., member discounts on purchases; a share of surplus allocated to community benefit; no economic return beyond the refund of membership fee]. Class C members do not participate in patronage distributions unless the Bylaws provide otherwise.</p>
<p><strong>Membership Fee Refund.</strong> Upon voluntary withdrawal, a Class C member shall receive a refund of their initial membership fee, without interest, within [PERIOD] of notice, subject to the association's financial capacity.</p>

<h2>GOVERNANCE SUMMARY TABLE</h2>
<table border='1' cellpadding='6' cellspacing='0' style='width:100%;border-collapse:collapse;'>
<thead>
<tr><th>Feature</th><th>Class A (Worker)</th><th>Class B (Investor)</th><th>Class C (Community)</th></tr>
</thead>
<tbody>
<tr><td>Eligibility</td><td>Employees only</td><td>Any capital contributor</td><td>[Defined community]</td></tr>
<tr><td>Membership Fee</td><td>$[AMOUNT]</td><td>Min. $[AMOUNT]</td><td>$[AMOUNT]</td></tr>
<tr><td>Voting Rights</td><td>1 member, 1 vote (all matters)</td><td>Limited — specified matters only</td><td>Advisory / specified matters</td></tr>
<tr><td>Board Seats</td><td>Majority of seats</td><td>[NUMBER] seats</td><td>[NUMBER] seats</td></tr>
<tr><td>Patronage Rights</td><td>Yes — proportional to labor</td><td>Preferred Return only</td><td>None (or limited)</td></tr>
<tr><td>Max Voting Share</td><td>Majority (RCW 23.78.190)</td><td>&lt;50%</td><td>Varies</td></tr>
<tr><td>Capital Redemption</td><td>Per operating agreement</td><td>Per investment schedule</td><td>Fee refund only</td></tr>
</tbody>
</table>

<h2>IMPORTANT STATUTORY NOTES</h2>
<p>RCW 23.78.190 requires that patron members — those who transact business with the cooperative — retain majority voting control at all times. Class B Investor Members may not, individually or collectively, hold majority voting power. Any amendment to these class definitions that would alter voting rights requires a supermajority vote of each affected class and compliance with RCW 23.78.</p>
<p>Counsel familiar with RCW 23.78 should review these class definitions before adoption. The ULCA is relatively new in Washington and there is limited case law; careful drafting is essential.</p>

<h2>ADOPTION</h2>
<p>These Membership Class Definitions were adopted by the Board of Directors of {$name} on [DATE] and incorporated into the Bylaws effective [DATE].</p>
<p>
President: _______________________________ Date: _____________<br>
Secretary: _______________________________ Date: _____________
</p>
</article>";
}
