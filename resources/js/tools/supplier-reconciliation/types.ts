// Props shapes sent by the Supplier Reconciliation presenters.

export type Side = 'statement' | 'ledger';

export type Run = {
    id: string;
    expires_at: string;
    has_statement: boolean;
    has_ledger: boolean;
    reconciled: boolean;
    statement_name: string | null;
    ledger_name: string | null;
};

export type Limits = {
    max_file_mb: number;
    max_rows: number;
};

export type FileSummary = {
    name: string;
    size: number;
    format: 'csv' | 'xlsx';
    format_label: string;
    details: Record<string, string>;
    rows: number;
    columns: number;
    header_row: number;
    preview: {
        headers: string[];
        rows: string[][];
    };
};

export type Option = { value: string; label: string };

export type MappingValues = {
    header_index: number;
    columns: Record<string, number | null>;
    amount_mode: 'signed' | 'debit_credit';
    invoice_sign: 'positive' | 'negative';
    invoice_column: 'debit' | 'credit';
    date_order: 'dmy' | 'mdy';
    decimal_separator: 'dot' | 'comma';
    sign_from_type: boolean;
    supplier_filter: string | null;
};

export type MappingSide = {
    side: Side;
    label: string;
    file_name: string;
    header_index: number;
    header_choices: { index: number; row_number: number; text: string }[];
    columns: {
        index: number;
        name: string;
        letter: string;
        samples: string[];
    }[];
    fields: { key: string; label: string; help: string }[];
    mapping: MappingValues;
    supplier_values: string[];
    options: {
        amount_modes: Option[];
        invoice_signs: Option[];
        invoice_columns: Option[];
        date_orders: Option[];
        decimal_separators: Option[];
    };
};

export type SideReport = {
    rows: number;
    transactions: number;
    fields: { reference: boolean; date: boolean; amount: boolean };
    unreadable_amounts: number;
    unreadable_dates: number;
    without_reference: number;
    filtered_out: number;
    ignored_text_rows: number;
    conventions: string[];
    row_issues: { row: number; issues: string[] }[];
};

export type PreflightReport = {
    ready: boolean;
    blocking: string[];
    warnings: string[];
    sides: Record<Side, SideReport>;
    suggest_inverting_ledger_sign: boolean;
};

export type AmountTotal = { count: number; total: string };

export type Summary = {
    analyzed_lines: number;
    statement_lines: number;
    ledger_lines: number;
    excluded_lines: number;
    matched_automatically: { items: number; lines: number };
    cleared_automatically_percent: number;
    engine_attention_items: number;
    engine_counts: Record<string, number>;
    attention_items: number;
    attention_counts: Record<string, number>;
    resolutions: Record<string, number>;
    amounts: {
        missing_invoices: AmountTotal;
        missing_credits: AmountTotal;
        amount_differences: AmountTotal;
        ledger_only: AmountTotal;
    };
};

export type Polarity = 'agrees' | 'partial' | 'differs' | 'info';

export type Reason = { code: string; polarity: Polarity; message: string };

export type Comparison = {
    field: 'reference' | 'amount' | 'date';
    statement_original: string | null;
    statement_normalized: string | null;
    ledger_original: string | null;
    ledger_normalized: string | null;
    relation: string;
    polarity: Polarity;
};

export type Transaction = {
    id: string;
    side: Side;
    row: number;
    reference: string | null;
    reference_normalized: string | null;
    date: string | null;
    date_normalized: string | null;
    amount: string | null;
    debit: string | null;
    credit: string | null;
    amount_normalized: string | null;
    type: string;
    type_original: string | null;
    description: string | null;
    issues: string[];
    amount_notes: string[];
};

export type Candidate = {
    statement_ids: string[];
    ledger_ids: string[];
    comparisons: Comparison[];
    reasons: Reason[];
};

export type ItemAction =
    | 'confirm'
    | 'reject'
    | 'choose'
    | 'manual_match'
    | 'defer'
    | 'undo';

export type ReviewItem = {
    id: string;
    status: string;
    status_label: string;
    engine_status: string;
    engine_status_label: string;
    kind: string | null;
    kind_label: string | null;
    confidence: string | null;
    resolution: string;
    resolution_label: string;
    needs_attention: boolean;
    headline: string;
    statement: Transaction[];
    ledger: Transaction[];
    reasons: Reason[];
    candidates: Candidate[];
    difference: string | null;
    flags: string[];
    actions: ItemAction[];
    decision: {
        id: string;
        action: string;
        label: string;
        decided_at: string;
    } | null;
};

export type Filter = { key: string; label: string; count: number };

export type MatchOptions = {
    item_id: string;
    options: Transaction[];
} | null;
