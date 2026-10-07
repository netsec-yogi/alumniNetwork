/** Shared look for text-like controls, so every input, select and textarea match. */
export const controlBase =
    'block w-full rounded-md border-0 bg-surface text-sm text-ink shadow-xs ring-1 ring-inset placeholder:text-subtle transition-shadow focus:ring-2 focus:ring-inset disabled:cursor-not-allowed disabled:bg-surface-muted disabled:text-muted read-only:bg-surface-muted';

export const controlTone = (invalid: boolean) => (invalid ? 'ring-red-400 focus:ring-red-500' : 'ring-line-strong focus:ring-brand-500');
