from __future__ import annotations

import argparse
import shutil
from pathlib import Path

from docx import Document
from docx.enum.section import WD_SECTION
from docx.enum.style import WD_STYLE_TYPE
from docx.enum.table import WD_ALIGN_VERTICAL, WD_CELL_VERTICAL_ALIGNMENT, WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK, WD_LINE_SPACING
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor


INK = "13233A"
MUTED = "66758A"
TEAL = "0F766E"
TEAL_SOFT = "E4F4F1"
LIGHT = "F1F4F7"
LINE = "B9C2CC"
RED = "B42318"


def set_font(run, name: str = "Arial", size: float | None = None, bold=None, color: str | None = None):
    run.font.name = name
    run._element.get_or_add_rPr().rFonts.set(qn("w:ascii"), name)
    run._element.get_or_add_rPr().rFonts.set(qn("w:hAnsi"), name)
    if size is not None:
        run.font.size = Pt(size)
    if bold is not None:
        run.bold = bold
    if color is not None:
        run.font.color.rgb = RGBColor.from_string(color)


def clear_body(document: Document) -> None:
    body = document._element.body
    for child in list(body):
        if child.tag != qn("w:sectPr"):
            body.remove(child)


def set_cell_shading(cell, fill: str) -> None:
    tc_pr = cell._tc.get_or_add_tcPr()
    shd = tc_pr.find(qn("w:shd"))
    if shd is None:
        shd = OxmlElement("w:shd")
        tc_pr.append(shd)
    shd.set(qn("w:fill"), fill)


def set_cell_margins(cell, top=90, start=110, bottom=90, end=110) -> None:
    tc = cell._tc
    tc_pr = tc.get_or_add_tcPr()
    tc_mar = tc_pr.first_child_found_in("w:tcMar")
    if tc_mar is None:
        tc_mar = OxmlElement("w:tcMar")
        tc_pr.append(tc_mar)
    for margin, value in (("top", top), ("start", start), ("bottom", bottom), ("end", end)):
        node = tc_mar.find(qn(f"w:{margin}"))
        if node is None:
            node = OxmlElement(f"w:{margin}")
            tc_mar.append(node)
        node.set(qn("w:w"), str(value))
        node.set(qn("w:type"), "dxa")


def set_repeat_table_header(row) -> None:
    tr_pr = row._tr.get_or_add_trPr()
    tbl_header = OxmlElement("w:tblHeader")
    tbl_header.set(qn("w:val"), "true")
    tr_pr.append(tbl_header)


def set_row_cant_split(row) -> None:
    tr_pr = row._tr.get_or_add_trPr()
    if tr_pr.find(qn("w:cantSplit")) is None:
        tr_pr.append(OxmlElement("w:cantSplit"))


def set_table_borders(table, color: str = LINE, size: str = "5") -> None:
    tbl_pr = table._tbl.tblPr
    borders = tbl_pr.first_child_found_in("w:tblBorders")
    if borders is None:
        borders = OxmlElement("w:tblBorders")
        tbl_pr.append(borders)
    for edge in ("top", "left", "bottom", "right", "insideH", "insideV"):
        el = borders.find(qn(f"w:{edge}"))
        if el is None:
            el = OxmlElement(f"w:{edge}")
            borders.append(el)
        el.set(qn("w:val"), "single")
        el.set(qn("w:sz"), size)
        el.set(qn("w:color"), color)


def add_body(document: Document, text: str, *, bold_lead: str | None = None, keep=False):
    p = document.add_paragraph(style="Normal")
    p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
    p.paragraph_format.space_after = Pt(6)
    p.paragraph_format.line_spacing = 1.15
    p.paragraph_format.keep_together = keep
    if bold_lead and text.startswith(bold_lead):
        lead = p.add_run(bold_lead)
        set_font(lead, bold=True)
        rest = p.add_run(text[len(bold_lead):])
        set_font(rest)
    else:
        run = p.add_run(text)
        set_font(run)
    return p


def add_bullets(document: Document, items: list[str]) -> None:
    for item in items:
        # The supplied assignment template deliberately contains only a very
        # small style set, so do not depend on Word's optional List Bullet
        # style.  A literal bullet keeps the document portable while retaining
        # the same hanging-indent layout.
        p = document.add_paragraph(style="Normal")
        p.paragraph_format.left_indent = Inches(0.25)
        p.paragraph_format.first_line_indent = Inches(-0.18)
        p.paragraph_format.space_after = Pt(3)
        set_font(p.add_run("\u2022 "))
        set_font(p.add_run(item))


def add_heading(document: Document, text: str, level: int, *, page_break=False):
    p = document.add_paragraph(style=f"Heading {level}")
    p.paragraph_format.keep_with_next = True
    p.paragraph_format.space_before = Pt(10 if level == 1 else 7)
    p.paragraph_format.space_after = Pt(5)
    if page_break:
        p.paragraph_format.page_break_before = True
    run = p.add_run(text)
    set_font(run, size=13 if level == 1 else 12, bold=True, color=INK)
    return p


def add_caption(document: Document, label: str, text: str):
    p = document.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Pt(3)
    p.paragraph_format.space_after = Pt(8)
    p.paragraph_format.keep_with_next = False
    r1 = p.add_run(label + ". ")
    set_font(r1, size=9, bold=True, color=INK)
    r2 = p.add_run(text)
    set_font(r2, size=9, color=MUTED)


def add_figure(document: Document, path: Path, label: str, caption: str, width: float = 6.15, alt_text: str = ""):
    p = document.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Pt(5)
    p.paragraph_format.space_after = Pt(0)
    run = p.add_run()
    inline = run.add_picture(str(path), width=Inches(width))
    inline._inline.docPr.set("descr", alt_text or caption)
    add_caption(document, label, caption)


def add_code(document: Document, code: str, label: str | None = None):
    if label:
        p_label = document.add_paragraph()
        p_label.paragraph_format.space_before = Pt(4)
        p_label.paragraph_format.space_after = Pt(2)
        p_label.paragraph_format.keep_with_next = True
        set_font(p_label.add_run(label), size=9, bold=True, color=TEAL)
    p = document.add_paragraph()
    p.paragraph_format.left_indent = Inches(0.16)
    p.paragraph_format.right_indent = Inches(0.16)
    p.paragraph_format.space_before = Pt(2)
    p.paragraph_format.space_after = Pt(7)
    p.paragraph_format.keep_together = True
    p_pr = p._p.get_or_add_pPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:fill"), LIGHT)
    p_pr.append(shd)
    run = p.add_run(code)
    set_font(run, name="Consolas", size=8.2, color=INK)
    return p


def add_table(document: Document, headers: list[str], rows: list[list[str]], widths: list[float] | None = None, font_size=8.5):
    table = document.add_table(rows=1, cols=len(headers))
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table.autofit = False
    set_table_borders(table)
    header = table.rows[0]
    set_repeat_table_header(header)
    set_row_cant_split(header)
    for i, value in enumerate(headers):
        cell = header.cells[i]
        set_cell_shading(cell, "D9E2EC")
        set_cell_margins(cell)
        cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
        p = cell.paragraphs[0]
        p.paragraph_format.space_after = Pt(0)
        set_font(p.add_run(value), size=font_size, bold=True, color=INK)
        if widths:
            cell.width = Inches(widths[i])
    for row_values in rows:
        row = table.add_row()
        set_row_cant_split(row)
        for i, value in enumerate(row_values):
            cell = row.cells[i]
            set_cell_margins(cell)
            cell.vertical_alignment = WD_ALIGN_VERTICAL.CENTER
            p = cell.paragraphs[0]
            p.paragraph_format.space_after = Pt(0)
            p.paragraph_format.line_spacing = 1.0
            set_font(p.add_run(value), size=font_size)
            if widths:
                cell.width = Inches(widths[i])
    document.add_paragraph().paragraph_format.space_after = Pt(1)
    return table


def add_toc(document: Document):
    p = document.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.LEFT
    run = p.add_run()
    fld_begin = OxmlElement("w:fldChar")
    fld_begin.set(qn("w:fldCharType"), "begin")
    instr = OxmlElement("w:instrText")
    instr.set(qn("xml:space"), "preserve")
    instr.text = ' TOC \\o "1-3" \\h \\z \\u '
    fld_sep = OxmlElement("w:fldChar")
    fld_sep.set(qn("w:fldCharType"), "separate")
    placeholder = OxmlElement("w:t")
    placeholder.text = "Right-click and update field if the table of contents is not visible."
    fld_end = OxmlElement("w:fldChar")
    fld_end.set(qn("w:fldCharType"), "end")
    run._r.extend([fld_begin, instr, fld_sep, placeholder, fld_end])


def add_page_break(document: Document):
    p = document.add_paragraph()
    p.add_run().add_break(WD_BREAK.PAGE)


def build(reference: Path, output: Path, repo_root: Path) -> None:
    output.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(reference, output)
    document = Document(output)
    clear_body(document)

    section = document.sections[0]
    section.start_type = WD_SECTION.NEW_PAGE
    section.top_margin = Inches(1)
    section.bottom_margin = Inches(1)
    section.left_margin = Inches(1)
    section.right_margin = Inches(1)

    normal = document.styles["Normal"]
    normal.font.name = "Arial"
    normal._element.rPr.rFonts.set(qn("w:ascii"), "Arial")
    normal._element.rPr.rFonts.set(qn("w:hAnsi"), "Arial")
    normal.font.size = Pt(11)
    normal.paragraph_format.line_spacing = 1.15

    document.core_properties.title = "BMIT3173 Assignment Report - VoteTrust Election Management Module"
    document.core_properties.author = "Low Wen Kai"
    document.core_properties.subject = "MVC, ORM, State Design Pattern, Secure Coding and REST Web Services"

    # Cover page
    for _ in range(2):
        document.add_paragraph()
    p = document.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    set_font(p.add_run("BMIT3173 Integrative Programming"), size=21, bold=True, color=INK)
    p.paragraph_format.space_after = Pt(22)
    p = document.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    set_font(p.add_run("ASSIGNMENT 202605"), size=17, bold=True, color=INK)
    p.paragraph_format.space_after = Pt(38)

    metadata = [
        ("Student Name", "Low Wen Kai"),
        ("Student ID", "—"),
        ("Programme", "—"),
        ("Tutorial Group", "—"),
        ("System Title", "VoteTrust Online Election Management System"),
        ("Chosen SDG", "SDG 16: Peace, Justice and Strong Institutions"),
        ("Module", "Election Management"),
    ]
    table = document.add_table(rows=len(metadata), cols=2)
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table.autofit = False
    table.columns[0].width = Inches(1.55)
    table.columns[1].width = Inches(4.25)
    tbl_pr = table._tbl.tblPr
    borders = OxmlElement("w:tblBorders")
    for edge in ("top", "left", "bottom", "right", "insideH", "insideV"):
        el = OxmlElement(f"w:{edge}")
        el.set(qn("w:val"), "nil")
        borders.append(el)
    tbl_pr.append(borders)
    for row, (label, value) in zip(table.rows, metadata):
        for cell in row.cells:
            set_cell_margins(cell, top=95, bottom=95)
        p0 = row.cells[0].paragraphs[0]
        p1 = row.cells[1].paragraphs[0]
        set_font(p0.add_run(label), size=11, bold=True, color=INK)
        set_font(p1.add_run(":  " + value), size=11, color=INK if value != "—" else RED)

    add_page_break(document)
    add_heading(document, "Table of Contents", 1)
    add_toc(document)
    add_page_break(document)

    # 1. Introduction
    add_heading(document, "1. Introduction to the System", 1)
    add_body(document, "VoteTrust is a web-based Online Election Management System that enables institutions, clubs, associations, and community organisations to host their own elections. Rather than conducting one election, the platform provides reusable infrastructure for organisers to configure schedules and voter rules, candidates to register for a specific election, verified voters to cast one vote, and payment records to unlock fee-dependent actions. The result is a traceable workflow from election setup through nomination, voting, closure, and audit.")
    add_body(document, "This individual submission implements the Election Management Module. An organizer creates an election, configures its rules and dates, and verifies the hosting payment. The election then waits for review by a separate system administrator; only approval opens candidate nomination. The module exposes availability to Candidate and Voting modules, consumes confirmation from the Payment module, and exposes a protected approval endpoint for the Admin module. Candidate applications, voter accounts, vote storage, payment processing, and general administration remain owned by the respective teammates' modules.")
    add_heading(document, "1.1 Chosen Sustainable Development Goal", 2)
    add_body(document, "The selected goal is SDG 16: Peace, Justice and Strong Institutions. The United Nations includes effective, accountable institutions and responsive, inclusive, participatory decision-making within Goal 16 (United Nations Department of Economic and Social Affairs, n.d.). VoteTrust supports this direction at an organisational scale by making election rules, deadlines, payment gates, action availability, and lifecycle changes explicit and auditable.")
    add_body(document, "The target users are organisers, candidates, eligible voters, and administrators in institutions or community organisations. The contribution is intentionally scoped: software cannot guarantee political fairness by itself, but it can reduce avoidable manipulation by enforcing one consistent process, exposing the same lifecycle truth to other modules, and retaining a request-level audit trail for accountability.")

    # 2. Module Description
    add_heading(document, "2. Module Description", 1, page_break=True)
    add_heading(document, "2.1 Authentication, Organizer Role and Admin Role", 2)
    add_body(document, "Users authenticate with the username in the shared user table. Login regenerates the PHP session ID; cookies are HttpOnly and SameSite=Lax. An organizer has a matching organizer row, whereas an administrator independently requires user.is_admin = 1. Thus OrganizerAdmin is only a username, not an automatic admin role. LowWenKaiOrganizer manages owned elections and SystemAdmin reviews paid requests; self-approval is rejected.")
    add_heading(document, "2.2 Election Creation and Configuration", 2)
    add_body(document, "The create and edit functions capture the election title, description, voter eligibility rules, nomination dates, voting dates, hosting fee, and currency. ElectionValidator performs server-side length, numeric, currency, date-format, and chronological validation. A valid submission creates an Election entity in DRAFT through the ORM. Configuration remains editable only in DRAFT; submitting for payment locks the setup and prevents post-payment rule changes.")
    add_bullets(document, [
        "Nomination start must be earlier than nomination end.",
        "Voting start cannot precede nomination end, and voting end must be later than voting start.",
        "Hosting fee must be within the permitted range and use a three-letter currency code.",
        "All SQL values use PDO prepared statements through the ORM; HTML output is encoded with htmlspecialchars().",
    ])
    add_heading(document, "2.3 Lifecycle, Payment, Approval and Monitoring", 2)
    add_body(document, "The organizer console shows lifecycle, payment, approval, module availability, next actions, and audit history. Critical forms require the current password. ElectionService locks the shared row, delegates to its State, saves with an optimistic version check, and appends the transition record in one transaction.")
    add_body(document, "Confirmed payment records a snapshot and stops at PENDING_ADMIN_APPROVAL. The admin queue provides independent review. Approval records its actor/time and opens nomination; rejection records its actor/time/reason and becomes terminal. The embedded audit history includes request, actor, action, old/new states, metadata, and time.")
    add_code(document, "DRAFT -> PENDING_PAYMENT -> PENDING_ADMIN_APPROVAL\n      -> NOMINATION_OPEN -> VOTING_OPEN -> CLOSED\n\nCANCELLED and REJECTED are terminal.", "Implemented election lifecycle")
    add_heading(document, "2.4 Integration Boundary", 2)
    add_body(document, "Candidate registration and payment belong to teammate modules. This module exposes registration/voting availability, consumes PaymentGateway verification, and exposes protected admin approval. The local payment sandbox follows the same contract; teammate integration needs only environment configuration.")

    # 3. Entity Classes and ORM
    add_heading(document, "3. Entity Classes and ORM", 1, page_break=True)
    add_body(document, "Figure 1 is an entity class diagram rather than a duplicate ERD. It shows how ORM objects navigate the team's fixed schema. User::organizer() returns an Organizer object, Organizer::elections() returns Election objects, and Election::approvedBy() returns the reviewing User. TransitionRecord and PaymentVerification are value objects reconstructed from columns of the shared election row.")
    add_figure(document, repo_root / "docs/ENTITY_CLASS_DIAGRAM.png", "Figure 1", "Shared-table ORM entity classes and embedded audit/payment value objects. Source: docs/ENTITY_CLASS_DIAGRAM.puml.", 4.5, "Shared database ORM class diagram")
    add_table(document, ["Entity", "Responsibility", "Object references"], [
        ["User", "Shared login identity, adaptive password hash and admin flag.", "organizer(): Organizer?"],
        ["Organizer", "Shared organizer role attached to a User ID.", "user(): User; elections(): Election[]"],
        ["Election", "Configuration, payment, approval, lifecycle and optimistic version.", "organizer(): User; approvedBy(): User?"],
        ["Value objects", "Read-only audit history and payment response snapshot.", "Reconstructed from election JSON/columns"],
    ], [1.05, 3.25, 1.95], 8.1)
    add_heading(document, "3.1 ORM Implementation", 2)
    add_body(document, "A dependency-free Active Record ORM was implemented because the assignment permits plain PHP while requiring ORM. Model maps each entity to an existing table, enforces fillable attributes, hydrates rows into objects, and performs create/update/delete and optimistic locking. Query provides chainable where, orderBy, limit, get, first, and count operations. Identifiers are restricted to internal safe names and values are bound through PDO::prepare() (PHP Documentation Group, n.d.-b).")
    add_code(document, "public function organizer(): ?User\n{\n    return User::find((int) $this->get('organizer_id'));\n}\n\npublic function approvedBy(): ?User\n{\n    $id = (int) $this->get('approved_by', 0);\n    return $id > 0 ? User::find($id) : null;\n}", "Code excerpt 1. Object-reference relationships in app/Models/Election.php")
    add_body(document, "The submitted database structure was treated as a team integration constraint. The implementation preserves exactly candidacy, candidate, election, organizer, payment, user, vote, and voter. Only columns were added to user and election. The former lwk_users, lwk_elections, lwk_payment_verifications, and lwk_election_transition_logs were migrated, verified, backed up, and removed. Audit history and the payment response are embedded in the election row rather than requiring new tables.")
    add_body(document, "The Election entity declares version as its optimistic-lock column. Each update includes both id and the previously read version in its WHERE clause. If another request has already advanced the row, rowCount() is zero and ConcurrencyException is thrown. This protects the audit/state invariant from stale simultaneous organiser requests.")

    # 4. State Pattern
    add_heading(document, "4. Design Pattern", 1, page_break=True)
    add_heading(document, "4.1 Description of the State Design Pattern", 2)
    add_body(document, "The State pattern allows an object to change its behaviour when its internal state changes because behaviour is delegated to a concrete state object (Gamma et al., 1994). ElectionLifecycle is the Context, ElectionState is the State interface, and eight concrete classes represent DRAFT, PENDING_PAYMENT, PENDING_ADMIN_APPROVAL, NOMINATION_OPEN, VOTING_OPEN, CLOSED, CANCELLED, and REJECTED.")
    add_body(document, "Status is therefore not only a label. Each State owns its available actions, action handler, and Candidate/Voting capabilities. PendingPaymentState alone calls the payment interface. PendingAdminApprovalState alone accepts approve/reject, requires an admin, rejects self-approval, and checks that payment exists. NominationOpenState and VotingOpenState enforce schedule rules. ClosedState, CancelledState, and RejectedState reject further changes.")
    add_heading(document, "4.2 Implementation and Justification", 2)
    add_figure(document, repo_root / "docs/STATE_PATTERN.png", "Figure 2", "State-pattern class diagram including the independent administrator approval gate. Source: docs/STATE_PATTERN.puml.", 6.1, "Eight-state election design pattern class diagram")
    add_code(document, "public function transition(string $action, TransitionContext $context): void\n{\n    $this->state->apply($this, $action, $context);\n}\n\npublic function moveTo(string $stateName, string $action, array $metadata = []): void\n{\n    $from = $this->state->name();\n    $next = $this->factory->make($stateName);\n    $this->election->set('status', $next->name());\n    $this->state = $next;\n    $this->lastTransition = compact('action', 'from', 'metadata') + ['to' => $next->name()];\n}", "Code excerpt 2. Context delegation in app/States/ElectionLifecycle.php")
    add_body(document, "State was chosen because election behaviour changes fundamentally over time and invalid transitions are security-relevant. A controller-level switch statement would grow whenever a new stage or rule was added and could be inconsistently copied into APIs. Concrete State classes localise behaviour, allow unit testing of every transition, and let both the website and exposed API ask the same object whether candidate registration or voting is allowed. The Factory reconstructs the correct behaviour from the persisted state after each request.")
    add_table(document, ["Current state", "Allowed next action", "Enforced rule"], [
        ["DRAFT", "Submit for payment / cancel", "Configuration validated before it is locked."],
        ["PENDING_PAYMENT", "Verify payment / cancel", "Reference, payer, amount, currency and IFA envelope must match."],
        ["PENDING_ADMIN_APPROVAL", "Admin approve/reject; organizer withdraw", "Paid; reviewer is admin and not election owner."],
        ["NOMINATION_OPEN", "Open voting / cancel", "Payment and admin approval exist; schedule reached."],
        ["VOTING_OPEN", "Close / emergency cancel", "Configured voting end reached before normal closure."],
        ["CLOSED / CANCELLED / REJECTED", "None", "Terminal states reject all transitions."],
    ], [1.25, 1.9, 3.1], 8.3)

    # 5. Security
    add_heading(document, "5. Software Security", 1, page_break=True)
    add_body(document, "Input validation, prepared statements, output encoding, CSRF tokens, secure session cookies, ownership checks, Content Security Policy, and generic error handling are baseline controls in every workflow. They are not counted as the two selected practices below. The selected practices directly address threats unique to election management.")
    add_heading(document, "5.1 Potential Threats/Attacks", 2)
    add_body(document, "Threat 1 - Unauthorized organising through an unattended or hijacked session. A valid session alone would allow an attacker with temporary browser access, a stolen session identifier, or a successful CSRF chain to publish, open voting, close, or cancel an election. The impact includes premature voting, denial of participation, reputational harm, and loss of trust. OWASP specifically identifies sensitive transactions as a case for requiring current credentials again (OWASP Foundation, n.d.-a).", bold_lead="Threat 1 - Unauthorized organising through an unattended or hijacked session.")
    add_body(document, "Threat 2 - Payment, lifecycle and approval bypass. A client can alter form fields or call a transition URL directly. If the server trusts a target status, an unpaid election could be published, an organizer could approve their own request, voting could open too early, or a closed election could be reopened. A forged payment response could also attach another payer's reference or a lower amount. This is a business-logic attack: individual inputs may be valid while the requested sequence and actor are not.", bold_lead="Threat 2 - Payment, lifecycle and approval bypass.")
    add_heading(document, "5.2 Secure Coding Practices and Before/After Evidence", 2)
    add_table(document, ["Scenario", "Before secure practice", "After secure practice"], [
        ["Critical operation", "A valid session changes lifecycle state without current credentials.", "ReauthenticationGuard requires password_verify(); HTTP 403 and no state change on failure."],
        ["Payment/approval lifecycle", "Organizer writes NOMINATION_OPEN directly and bypasses review.", "Payment stops at the admin State; role and self-approval checks precede nomination."],
    ], [1.25, 2.45, 2.55], 8.2)
    add_code(document, "BEFORE  No password supplied                        NOMINATION_OPEN\nBEFORE  Organiser bypasses admin approval           NOMINATION_OPEN (VULNERABLE)\nAFTER   No password supplied                        BLOCKED (PASSWORD REQUIRED)\nAFTER   Unconfirmed payment supplied                PENDING_PAYMENT (PAYMENT GATE HELD)\nAFTER   Confirmed payment supplied                  PENDING_ADMIN_APPROVAL (ADMIN GATE HELD)\nAFTER   Organiser attempts self-approval            PENDING_ADMIN_APPROVAL (ORGANISER FORBIDDEN)\nAFTER   Independent administrator approves          NOMINATION_OPEN", "Verified terminal evidence: C:\\xampp\\php\\php.exe tests\\security_demo.php")
    add_heading(document, "Practice 1: Reauthenticate Before Critical Operations", 3)
    add_body(document, "Every transition form asks for the current password. ElectionService performs ownership checks and invokes ReauthenticationGuard before starting the transition transaction. password_verify() uses the algorithm and salt stored in the adaptive hash and is designed to be timing-attack safe (PHP Documentation Group, n.d.-a). The password is never written to old-input flash data or the audit log. Failed and successful challenges record only user ID and operation.")
    add_code(document, "public function assertPassword(User $user, string $password, string $operation): void\n{\n    if ($password === '' || !password_verify($password, $user->passwordHash())) {\n        $this->logger->log('critical_operation_reauthentication_failed', [\n            'user_id' => $user->id(), 'operation' => $operation,\n        ]);\n        throw new AuthorizationException(\n            'Your current password is required before this critical operation can continue.'\n        );\n    }\n}", "Code excerpt 3. app/Security/ReauthenticationGuard.php")
    add_heading(document, "Practice 2: Enforce Payment, State and Independent Approval", 3)
    add_body(document, "The server accepts an action, never a target status. PendingPaymentState calls the Payment REST service and rejects timeouts, malformed envelopes, mismatched request IDs, unconfirmed status, wrong reference, wrong payer, wrong amount, or wrong currency. A complete match stores the verification and moves only to PENDING_ADMIN_APPROVAL. PendingAdminApprovalState then requires user.is_admin = 1 and rejects the election owner. Approval, optimistic version update, and actor-attributed JSON audit append share one transaction.")
    add_code(document, "$paid = $response['status'] === 'S'\n    && $data['paymentStatus'] === 'CONFIRMED'\n    && $amountMatches\n    && $currencyMatches\n    && $payerMatches\n    && $referenceMatches;\n\nif (!$result->paid) {\n    throw new BusinessRuleException(\n        'Payment is not confirmed or does not match the election hosting fee.'\n    );\n}", "Code excerpt 4. HttpPaymentGateway and PendingPaymentState payment gate")
    add_code(document, "if (!$context->actor->isAdmin()) {\n    throw new AuthorizationException('Only a system administrator can approve or reject.');\n}\nif ($election->ownedBy($context->actor)) {\n    throw new AuthorizationException('An administrator cannot approve their own election.');\n}\n$lifecycle->moveTo(Election::NOMINATION_OPEN, 'approve');", "Code excerpt 5. Separation of duties in PendingAdminApprovalState.php")
    add_heading(document, "5.3 Verification Results", 2)
    add_body(document, "Eight repeatable unit/integration tests passed: full payment/admin/voting lifecycle, skipped-state rejection, reauthentication, ownership, pending-payment gate, separation of duties, optimistic locking, and IFA validation. A real HTTP script also passed organizer login, confirmed payment stopping at the admin gate, blocked organizer self-approval, admin web approval, and admin API approval. This demonstrates behaviour through routed controllers rather than only calling classes directly.")

    # 6. Web services
    add_heading(document, "6. Web Services", 1, page_break=True)
    add_body(document, "The module uses REST because JSON over HTTP gives the team a small, language-neutral boundary and allows each module to evolve independently. Fielding (2000) describes REST as an architectural style for network-based software with general interfaces and independently deployable components. The implementation follows the assignment IFA by requiring requestID/timestamp in requests and status/timestamp in responses.")
    add_heading(document, "6.1 Service Exposure: Election Availability", 2)
    add_table(document, ["IFA item", "Agreement"], [
        ["Protocol", "REST over HTTP; JSON response"],
        ["Function description", "Returns lifecycle, payment, admin approval and Candidate/Voting availability."],
        ["Source module", "Election Management - Low Wen Kai"],
        ["Target modules", "Candidate Registration, Voting Process, User/Voter Management"],
        ["URL", "GET /api/v1/elections/{electionId}/availability"],
        ["Function name", "getElectionAvailability"],
    ], [1.45, 4.8], 8.5)
    add_table(document, ["Field", "Type", "M/O", "Description", "Format"], [
        ["electionId", "Integer", "M", "Election ID in URL", "Positive integer"],
        ["requestID", "String", "M", "Unique trace ID", "8-64 alphanumeric, _ or -"],
        ["timestamp", "String", "M", "Request time", "ISO 8601; <= 5 minutes old"],
    ], [1.0, 0.7, 0.55, 2.1, 1.9], 7.7)
    add_table(document, ["Response field", "Type", "M/O", "Description", "Example"], [
        ["status", "String", "M", "S success, F failure, E error", "S"],
        ["requestID", "String", "M", "Echoed trace ID", "CANDIDATE-E2E-001"],
        ["timestamp", "String", "M", "Response generation time", "ISO 8601"],
        ["data.lifecycleState", "String", "S", "Persisted State", "VOTING_OPEN"],
        ["data.administratorApproved", "Boolean", "S", "Independent approval gate", "true"],
        ["data.candidateRegistrationAllowed", "Boolean", "S", "Candidate module gate", "false"],
        ["data.votingAllowed", "Boolean", "S", "Voting module gate", "true"],
    ], [1.5, 0.7, 0.55, 2.05, 1.45], 7.4)
    add_code(document, "{\n  \"status\": \"S\",\n  \"requestID\": \"CANDIDATE-E2E-001\",\n  \"timestamp\": \"2026-08-01T19:33:10+08:00\",\n  \"data\": {\n    \"lifecycleState\": \"NOMINATION_OPEN\",\n    \"hostingPaymentVerified\": true,\n    \"administratorApproved\": true,\n    \"candidateRegistrationAllowed\": true,\n    \"votingAllowed\": false\n  }\n}", "Availability response example")
    add_heading(document, "6.2 Service Consumption: Hosting Payment Verification", 2)
    add_body(document, "ElectionService depends on the PaymentGateway interface, while HttpPaymentGateway implements the REST call with PHP cURL. The local service on port 8081 is a functional IFA sandbox until the Payment teammate provides the final endpoint. Switching PAYMENT_API_URL and PAYMENT_API_KEY changes only configuration, so no controller, State, view, or ORM entity needs modification.")
    add_table(document, ["IFA item", "Agreement"], [
        ["Protocol", "REST over HTTP; JSON request and response"],
        ["Function description", "Verifies exact organizer hosting payment before admin review."],
        ["Source module", "Payment - Risky Puah Kaho (contract sandbox during development)"],
        ["Target module", "Election Management - Low Wen Kai"],
        ["URL", "POST {PAYMENT_API_URL}"],
        ["Function name", "verifyHostingPayment"],
        ["Authentication", "X-API-Key header"],
    ], [1.45, 4.8], 8.5)
    add_table(document, ["Request field", "Type", "M/O", "Description", "Format"], [
        ["requestID", "String", "M", "Cryptographically random trace ID", "EL- plus random hex"],
        ["timestamp", "String", "M", "Request time", "ISO 8601"],
        ["paymentReference", "String", "M", "Organiser payment reference", "8-64 safe characters"],
        ["purpose", "String", "M", "Payment purpose", "ELECTION_HOSTING"],
        ["payerId", "Integer", "M", "Organiser identifier", "Positive integer"],
        ["electionId", "Integer", "M", "Election to unlock", "Positive integer"],
        ["expectedAmount", "Number", "M", "Configured hosting fee", "Decimal"],
        ["currency", "String", "M", "Fee currency", "ISO-like 3 letters"],
    ], [1.45, 0.65, 0.55, 2.2, 1.4], 7.2)
    add_table(document, ["Response field", "Type", "M/O", "Validation"], [
        ["status", "String", "M", "Must be S before payment can pass."],
        ["requestID", "String", "M", "Must exactly equal request ID."],
        ["timestamp", "String", "M", "Must be present in IFA response."],
        ["data.paymentStatus", "String", "M", "Must equal CONFIRMED."],
        ["data.paymentReference", "String", "M", "Constant-time equality with supplied reference."],
        ["data.payerId", "Integer", "M", "Must equal organiser ID."],
        ["data.amount / currency", "Number / String", "M", "Must equal configured fee and currency."],
    ], [1.7, 0.95, 0.6, 3.0], 8.0)
    add_body(document, "A confirmed Payment response never publishes the election. It sets the payment snapshot and advances only to PENDING_ADMIN_APPROVAL. This preserves the tutor-confirmed process in which the organizer pays and the administrator decides whether the election may start.")
    add_heading(document, "6.3 Service Exposure: Administrator Approval", 2)
    add_table(document, ["IFA item", "Agreement"], [
        ["Protocol", "REST over HTTP; JSON request and response"],
        ["Function description", "Trusted Admin Module approves or rejects a paid election."],
        ["Source module", "Election Management - Low Wen Kai"],
        ["Target module", "System Administration"],
        ["URL", "POST /api/v1/elections/{electionId}/approval"],
        ["Authentication", "X-Admin-API-Key plus shared admin user ID"],
    ], [1.45, 4.8], 8.5)
    add_table(document, ["Request field", "Type", "M/O", "Rule"], [
        ["requestID", "String", "M", "8-64 safe trace characters"],
        ["timestamp", "String", "M", "ISO 8601 and no more than five minutes old"],
        ["adminUserId", "Integer", "M", "Existing user with is_admin = 1"],
        ["decision", "String", "M", "APPROVE or REJECT"],
        ["reason", "String", "R", "Required for rejection; 5-250 characters"],
    ], [1.45, 0.8, 0.55, 3.4], 8.0)
    add_body(document, "The endpoint verifies the API key with hash_equals(), validates the IFA envelope, loads the shared User entity, and delegates to ElectionService. The same PendingAdminApprovalState used by the web queue enforces role, payment, current state, and self-approval rules, preventing the two interfaces from drifting.")
    add_heading(document, "6.4 Failure and Reliability Scenarios", 2)
    add_bullets(document, [
        "A four-second connection and response timeout prevents a slow payment service from holding the request indefinitely.",
        "Non-2xx status, transport failure, or malformed JSON becomes a controlled business error; no state is changed.",
        "The IFA request ID is checked against the response to detect mismatched or replayed responses.",
        "Pending, failed, missing, wrong-payer, wrong-amount, wrong-currency, and wrong-reference results remain PENDING_PAYMENT.",
        "Wrong admin API key, non-admin actor, organizer self-approval, and review outside PENDING_ADMIN_APPROVAL are rejected.",
        "Optimistic locking rejects stale simultaneous transitions, while a database transaction keeps state, approval fields, and JSON audit history consistent.",
    ])

    # 7. Reflection
    add_heading(document, "7. Reflection and Independent Learning", 1)
    add_body(document, "The most important learning outcome was recognising that a lifecycle field is not sufficient by itself. My first mental model was a controller that checks status strings before each update. Researching the State pattern led me to move allowed actions and capabilities into concrete state classes. This reduced the controller to orchestration and produced one source of truth for both web screens and API availability.")
    add_body(document, "Implementing ORM without a framework also deepened my understanding of what ORM actually provides. Mapping rows to objects is only the starting point; mass-assignment boundaries, object relationships, prepared queries, and concurrency determine whether the abstraction is safe. Adapting the design from four private lwk_* tables to the team's fixed shared tables was especially valuable: I preserved ORM behaviour by representing payment and audit evidence as value objects backed by election columns. Adding a version column and a stale-update test directly addressed simultaneous requests.")
    add_body(document, "The security comparison changed how I would present secure coding. Commenting insecure production code in and out is easy to demonstrate but leaves ambiguity about which version was tested and risks exposing the weak path. I instead created a CLI-only legacy fixture and a repeatable before/after command. The website always uses the secure service, while the output clearly shows the two behavioural differences requested by the tutor.")
    add_body(document, "The service boundary highlighted dependency inversion. The election module does not need to know how Payment stores a payment; it needs a verified contract. PaymentGateway, strict IFA checks, timeouts, and environment configuration make local work possible and teammate integration later. The admin endpoint applies the same principle in the opposite direction. Remaining integration work is to agree final URLs/keys and run the existing contract tests against teammate services.")
    add_body(document, "Finally, automated and routed HTTP tests exposed cases that screenshots alone would miss: wrong passwords, payment rollback, illegal state skipping, cross-organizer access, self-approval, stale updates, and old timestamps. I would extend a production deployment with MFA for administrators, key rotation, and a distributed idempotency store.")

    # 8. References
    add_heading(document, "8. References", 1, page_break=True)
    references = [
        "Fielding, R. T. (2000). Architectural styles and the design of network-based software architectures (Doctoral dissertation, University of California, Irvine). https://ics.uci.edu/~fielding/pubs/dissertation/top.htm",
        "Fowler, M. (2002). Patterns of enterprise application architecture. Addison-Wesley.",
        "Gamma, E., Helm, R., Johnson, R., & Vlissides, J. (1994). Design patterns: Elements of reusable object-oriented software. Addison-Wesley.",
        "OpenAI. (2026). Codex [AI coding agent]. https://openai.com/codex/",
        "OWASP Foundation. (n.d.-a). Authentication cheat sheet. Retrieved August 1, 2026, from https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html",
        "OWASP Foundation. (n.d.-b). Transaction authorization cheat sheet. Retrieved August 1, 2026, from https://cheatsheetseries.owasp.org/cheatsheets/Transaction_Authorization_Cheat_Sheet.html",
        "PHP Documentation Group. (n.d.-a). password_verify. Retrieved August 1, 2026, from https://www.php.net/manual/en/function.password-verify.php",
        "PHP Documentation Group. (n.d.-b). PDO::prepare. Retrieved August 1, 2026, from https://www.php.net/manual/en/pdo.prepare.php",
        "PHP Documentation Group. (n.d.-c). cURL. Retrieved August 1, 2026, from https://www.php.net/manual/en/book.curl.php",
        "United Nations Department of Economic and Social Affairs. (n.d.). Goal 16: Peace, justice and strong institutions. Retrieved August 1, 2026, from https://sdgs.un.org/goals/goal16",
    ]
    for reference_text in references:
        p = document.add_paragraph(style="Normal")
        p.paragraph_format.left_indent = Inches(0.45)
        p.paragraph_format.first_line_indent = Inches(-0.45)
        p.paragraph_format.space_after = Pt(7)
        set_font(p.add_run(reference_text), size=10)

    add_heading(document, "8.1 AI Usage Disclosure Note", 2)
    add_body(document, "OpenAI Codex was used in a permitted assistance capacity to analyse the assignment artefacts, support implementation, execute tests, and help structure this report. The student remains responsible for reviewing the design, understanding the code, verifying the test evidence, completing the institution's AI Usage Disclosure Form, and explaining the implementation during assessment.")

    # Ask Word to update fields when the file opens.
    settings = document.settings._element
    update_fields = settings.find(qn("w:updateFields"))
    if update_fields is None:
        update_fields = OxmlElement("w:updateFields")
        settings.append(update_fields)
    update_fields.set(qn("w:val"), "true")

    document.save(output)


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--reference", required=True, type=Path)
    parser.add_argument("--output", required=True, type=Path)
    parser.add_argument("--repo-root", required=True, type=Path)
    args = parser.parse_args()
    build(args.reference.resolve(), args.output.resolve(), args.repo_root.resolve())


if __name__ == "__main__":
    main()
