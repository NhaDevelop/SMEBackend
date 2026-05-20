<?php

$tables = [
    'users' => [
        'color' => ['fill' => '#dae8fc', 'stroke' => '#6c8ebf'],
        'x' => 700, 'y' => 30,
        'cols' => [
            'PK id : bigint',
            'full_name : string',
            'email : string (unique)',
            'password : string',
            'phone : string (nullable)',
            'role : enum(SME,INVESTOR,ADMIN)',
            'status : enum(PENDING,ACTIVE,REJECTED)',
            'is_verified : boolean',
            'email_verified_at : timestamp (null)',
            'last_login_at : timestamp (null)',
            'remember_token : string',
            'created_at / updated_at',
        ],
    ],
    'sme_profiles' => [
        'color' => ['fill' => '#d5e8d4', 'stroke' => '#82b366'],
        'x' => 280, 'y' => 350,
        'cols' => [
            'PK id : bigint',
            'FK user_id → users',
            'company_name : string (null)',
            'registration_number : string (null)',
            'industry : string (null)',
            'stage : string (null)',
            'years_in_business : string (null)',
            'team_size : string (null)',
            'address : string (null)',
            'registration_document : string (null)',
            'readiness_score : decimal(5,2) (null)',
            'risk_level : string (null)',
            'founding_date : date (null)',
            'website_url : string (null)',
            'FK verified_by_user_id → users (null)',
            'verification_date : timestamp (null)',
            'created_at / updated_at',
        ],
    ],
    'investor_profiles' => [
        'color' => ['fill' => '#fff2cc', 'stroke' => '#d6b656'],
        'x' => 1150, 'y' => 350,
        'cols' => [
            'PK id : bigint',
            'FK user_id → users',
            'organization_name : string (null)',
            'registration_number : string (null)',
            'investor_type : string (null)',
            'industry : string (null)',
            'years_in_business : string (null)',
            'team_size : string (null)',
            'address : text (null)',
            'registration_document : string (null)',
            'min_ticket_size : decimal(15,2) (null)',
            'max_ticket_size : decimal(15,2) (null)',
            'preferred_industries : json (null)',
            'created_at / updated_at',
        ],
    ],
    'sectors' => [
        'color' => ['fill' => '#f8cecc', 'stroke' => '#b85450'],
        'x' => 1450, 'y' => 650,
        'cols' => [
            'PK id : bigint',
            'name : string',
            'description : text (null)',
            'color : string',
            'created_at / updated_at',
        ],
    ],
    'templates' => [
        'color' => ['fill' => '#f8cecc', 'stroke' => '#b85450'],
        'x' => 700, 'y' => 350,
        'cols' => [
            'PK id : bigint',
            'name : string',
            'version : string',
            'industry : string (null)',
            'description : text (null)',
            'status : string (Draft/Active/Archived)',
            'settings : json (null)',
            'deleted_at : timestamp (null)',
            'created_at / updated_at',
        ],
    ],
    'pillars' => [
        'color' => ['fill' => '#f8cecc', 'stroke' => '#b85450'],
        'x' => 1000, 'y' => 350,
        'cols' => [
            'PK id : bigint',
            'name : string',
            'weight : decimal(5,2)',
            'created_at / updated_at',
        ],
    ],
    'questions' => [
        'color' => ['fill' => '#f8cecc', 'stroke' => '#b85450'],
        'x' => 850, 'y' => 650,
        'cols' => [
            'PK id : bigint',
            'FK template_id → templates',
            'FK pillar_id → pillars',
            'text : text',
            'type : string (Yes/No, Scale, MC)',
            'weight : decimal(5,2)',
            'required : boolean',
            'options : json (null)',
            'helper_text : text (null)',
            'deleted_at : timestamp (null)',
            'created_at / updated_at',
        ],
    ],
    'framework_settings' => [
        'color' => ['fill' => '#f8cecc', 'stroke' => '#b85450'],
        'x' => 1450, 'y' => 350,
        'cols' => [
            'PK id : bigint',
            'key : string (unique)',
            'value : json',
            'created_at / updated_at',
        ],
    ],
    'programs' => [
        'color' => ['fill' => '#ffe6cc', 'stroke' => '#d79b00'],
        'x' => 430, 'y' => 650,
        'cols' => [
            'PK id : bigint',
            'name : string',
            'slug : string (unique, null)',
            'description : text (null)',
            'FK template_id → templates (null)',
            'FK created_by_user_id → users (null)',
            'status : string',
            'start_date : date (null)',
            'end_date : date (null)',
            'enrollment_deadline : timestamp (null)',
            'sector : string (null)',
            'duration : string (null)',
            'deadline : string (null)',
            'investment_amount : string (null)',
            'benefits : json (null)',
            'thresholds : json (null)',
            'created_at / updated_at',
        ],
    ],
    'program_enrollments' => [
        'color' => ['fill' => '#ffe6cc', 'stroke' => '#d79b00'],
        'x' => 600, 'y' => 1000,
        'cols' => [
            'PK id : bigint',
            'FK program_id → programs',
            'FK sme_id → sme_profiles (null)',
            'FK investor_id → investor_profiles (null)',
            'status : string',
            'enrollment_date : timestamp (null)',
            'created_at / updated_at',
        ],
    ],
    'program_comments' => [
        'color' => ['fill' => '#ffe6cc', 'stroke' => '#d79b00'],
        'x' => 30, 'y' => 650,
        'cols' => [
            'PK id : bigint',
            'FK program_id → programs',
            'FK user_id → users',
            'content : text',
            'created_at / updated_at',
        ],
    ],
    'investor_interests' => [
        'color' => ['fill' => '#fff2cc', 'stroke' => '#d6b656'],
        'x' => 1150, 'y' => 1000,
        'cols' => [
            'PK id : bigint',
            'FK investor_id → investor_profiles',
            'FK sme_id → sme_profiles',
            'notes : text (null)',
            'created_at / updated_at',
        ],
    ],
    'assessments' => [
        'color' => ['fill' => '#d5e8d4', 'stroke' => '#82b366'],
        'x' => 200, 'y' => 1000,
        'cols' => [
            'PK id : bigint',
            'FK sme_id → sme_profiles',
            'FK template_id → templates',
            'FK program_id → programs (null)',
            'status : string',
            'total_score : decimal(5,2)',
            'pillar_scores : json (null)',
            'questions_snapshot : json (null)',
            'started_at : timestamp',
            'completed_at : timestamp (null)',
            'created_at / updated_at',
        ],
    ],
    'assessment_responses' => [
        'color' => ['fill' => '#d5e8d4', 'stroke' => '#82b366'],
        'x' => 350, 'y' => 1320,
        'cols' => [
            'PK id : bigint',
            'FK assessment_id → assessments',
            'FK question_id → questions',
            'answer_value : json',
            'score_awarded : decimal(5,2)',
            'created_at / updated_at',
        ],
    ],
    'goals' => [
        'color' => ['fill' => '#d5e8d4', 'stroke' => '#82b366'],
        'x' => 30, 'y' => 1320,
        'cols' => [
            'PK id : bigint',
            'FK sme_id → sme_profiles',
            'FK pillar_id → pillars (null)',
            'FK created_by → users (null)',
            'FK verified_by → users (null)',
            'title : string',
            'description : text (null)',
            'status : string',
            'due_date : date (null)',
            'progress_percentage : integer',
            'target_score : decimal(5,2) (null)',
            'pillar_targets : json (null)',
            'proof_note : text (null)',
            'proof_document : string (null)',
            'proof_verified : boolean',
            'verified_at : timestamp (null)',
            'rejection_note : text (null)',
            'created_at / updated_at',
        ],
    ],
    'documents' => [
        'color' => ['fill' => '#d5e8d4', 'stroke' => '#82b366'],
        'x' => 30, 'y' => 350,
        'cols' => [
            'PK id : bigint',
            'FK sme_id → sme_profiles',
            'FK verified_by_user_id → users (null)',
            'name : string',
            'original_filename : string',
            'type : string (null)',
            'category : string (null)',
            'description : text (null)',
            'size : bigint (null)',
            'file_url : string',
            'is_verified : boolean',
            'uploaded_at : timestamp (null)',
            'created_at / updated_at',
        ],
    ],
    'messages' => [
        'color' => ['fill' => '#e1d5e7', 'stroke' => '#9673a6'],
        'x' => 1450, 'y' => 30,
        'cols' => [
            'PK id : bigint',
            'chat_id : string (indexed)',
            'FK sender_id → users',
            'FK receiver_id → users',
            'content : text',
            'read : boolean',
            'created_at / updated_at',
        ],
    ],
    'notifications' => [
        'color' => ['fill' => '#e1d5e7', 'stroke' => '#9673a6'],
        'x' => 1450, 'y' => 1000,
        'cols' => [
            'PK id : bigint',
            'FK user_id → users',
            'type : string (null)',
            'message : text',
            'channel : string (IN_APP)',
            'is_read : boolean',
            'created_at / updated_at',
        ],
    ],
    'verification_requests' => [
        'color' => ['fill' => '#f5f5f5', 'stroke' => '#666666'],
        'x' => 280, 'y' => 30,
        'cols' => [
            'PK id : bigint',
            'FK user_id → users',
            'document_type : string',
            'status : string (Pending/Approved/Rejected)',
            'evidence_link : string (null)',
            'notes : text (null)',
            'created_at / updated_at',
        ],
    ],
    'audit_logs' => [
        'color' => ['fill' => '#f5f5f5', 'stroke' => '#666666'],
        'x' => 1000, 'y' => 30,
        'cols' => [
            'PK id : bigint',
            'FK user_id → users (null)',
            'action : string',
            'target_entity : string (null)',
            'target_id : bigint (null)',
            'details : text (null)',
            'ip_address : string(45) (null)',
            'created_at / updated_at',
        ],
    ],
];

// Edges: [source_id, target_id, dashed, color]
$edges = [
    ['users', 'sme_profiles', false, '#6c8ebf'],
    ['users', 'investor_profiles', false, '#6c8ebf'],
    ['users', 'verification_requests', true, '#999999'],
    ['users', 'audit_logs', true, '#999999'],
    ['users', 'messages', true, '#9673a6'],
    ['users', 'notifications', true, '#9673a6'],
    ['users', 'program_comments', true, '#d79b00'],
    ['users', 'programs', true, '#d79b00'],
    ['users', 'documents', true, '#82b366'],
    ['users', 'goals', true, '#e67e22'],  // created_by / verified_by
    ['sme_profiles', 'assessments', false, '#82b366'],
    ['sme_profiles', 'goals', false, '#82b366'],
    ['sme_profiles', 'documents', false, '#82b366'],
    ['sme_profiles', 'program_enrollments', false, '#d79b00'],
    ['sme_profiles', 'investor_interests', false, '#d6b656'],
    ['investor_profiles', 'program_enrollments', false, '#d79b00'],
    ['investor_profiles', 'investor_interests', false, '#d6b656'],
    ['templates', 'programs', false, '#d79b00'],
    ['templates', 'questions', false, '#b85450'],
    ['templates', 'assessments', false, '#82b366'],
    ['pillars', 'questions', false, '#b85450'],
    ['pillars', 'goals', true, '#b85450'],
    ['programs', 'program_enrollments', false, '#d79b00'],
    ['programs', 'program_comments', false, '#d79b00'],
    ['programs', 'assessments', false, '#82b366'],
    ['assessments', 'assessment_responses', false, '#82b366'],
    ['questions', 'assessment_responses', false, '#82b366'],
];

// Build XML
$rowHeight = 20;
$headerHeight = 30;
$width = 290;

function tableHeight(array $cols): int {
    return 30 + count($cols) * 18 + 10;
}

function escapeXml(string $s): string {
    return htmlspecialchars($s, ENT_XML1, 'UTF-8');
}

$cells = [];
$cellId = 10;

// Table cells
foreach ($tables as $id => $t) {
    $colLines = implode('&#xa;', array_map('escapeXml', $t['cols']));
    $label = strtoupper($id) . '&#xa;' . str_repeat('─', 22) . '&#xa;' . $colLines;
    $h = tableHeight($t['cols']);
    $fill = $t['color']['fill'];
    $stroke = $t['color']['stroke'];
    $style = "shape=table;startSize=30;container=0;collapsible=0;childLayout=tableLayout;fixedRows=1;rowLines=0;fontStyle=1;align=center;resizeLast=1;fontSize=11;fillColor={$fill};strokeColor={$stroke};";
    $cells[] = "<mxCell id=\"{$id}\" parent=\"1\" style=\"{$style}\" value=\"&#xa;{$label}\" vertex=\"1\"><mxGeometry height=\"{$h}\" width=\"{$width}\" x=\"{$t['x']}\" y=\"{$t['y']}\" as=\"geometry\" /></mxCell>";
    $cellId++;
}

// Edge cells
$edgeId = 500;
foreach ($edges as $e) {
    [$src, $tgt, $dashed, $color] = $e;
    $dashedStr = $dashed ? 'dashed=1;' : '';
    $style = "edgeStyle=orthogonalEdgeStyle;endArrow=ERmany;startArrow=ERone;{$dashedStr}strokeColor={$color};";
    $cells[] = "<mxCell id=\"e{$edgeId}\" edge=\"1\" parent=\"1\" source=\"{$src}\" target=\"{$tgt}\" style=\"{$style}\"><mxGeometry relative=\"1\" as=\"geometry\" /></mxCell>";
    $edgeId++;
}

$allCells = implode("\n        ", $cells);

$xml = <<<XML
<mxfile host="app.diagrams.net">
  <diagram name="Full SME DB Schema (Complete)" id="full_schema">
    <mxGraphModel dx="2500" dy="2500" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="2800" pageHeight="3900" math="0" shadow="0">
      <root>
        <mxCell id="0" />
        <mxCell id="1" parent="0" />
        {$allCells}
      </root>
    </mxGraphModel>
  </diagram>
</mxfile>
XML;

file_put_contents(__DIR__ . '/sme_db_diagram_full.drawio', $xml);
echo "✅ Generated: sme_db_diagram_full.drawio\n";
echo "Tables: " . count($tables) . "\n";
echo "Edges: " . count($edges) . "\n";
