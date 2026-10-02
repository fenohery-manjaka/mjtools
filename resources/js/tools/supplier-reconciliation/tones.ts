// Visual tone of each status. Colour only: labels and meaning always come from the server.
export type Tone =
    | 'success'
    | 'info'
    | 'warning'
    | 'danger'
    | 'notice'
    | 'muted';

const statusTones: Record<string, Tone> = {
    matched: 'success',
    possible_match: 'info',
    ambiguous: 'warning',
    review_required: 'warning',
    missing_in_ledger: 'danger',
    ledger_only: 'warning',
    amount_mismatch: 'danger',
    duplicate_suspected: 'notice',
    excluded: 'muted',
};

export function toneOf(status: string): Tone {
    return statusTones[status] ?? 'muted';
}

// Soft background + strong text, for badges and highlighted blocks.
export const softClasses: Record<Tone, string> = {
    success: 'bg-success-soft text-success-strong',
    info: 'bg-info-soft text-info-strong',
    warning: 'bg-warning-soft text-warning-strong',
    danger: 'bg-danger-soft text-danger-strong',
    notice: 'bg-notice-soft text-notice-strong',
    muted: 'bg-muted text-muted-foreground',
};

// Solid dot / bar colour.
export const dotClasses: Record<Tone, string> = {
    success: 'bg-success',
    info: 'bg-info',
    warning: 'bg-warning',
    danger: 'bg-danger',
    notice: 'bg-notice',
    muted: 'bg-muted-foreground/50',
};

// Left accent border for cards.
export const edgeClasses: Record<Tone, string> = {
    success: 'border-l-success',
    info: 'border-l-info',
    warning: 'border-l-warning',
    danger: 'border-l-danger',
    notice: 'border-l-notice',
    muted: 'border-l-border',
};
