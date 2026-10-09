export interface AuthUser {
    id: number;
    name: string;
    email: string;
    email_verified: boolean;
    two_factor_enabled: boolean;
    two_factor_required: boolean;
    roles: string[];
    permissions: string[];
    can_access_admin: boolean;
    is_member: boolean;
    verification_status: 'pending' | 'verified' | 'rejected' | 'suspended' | 'archived' | null;
}

export interface Flash {
    status?: string | null;
    success?: string | null;
    warning?: string | null;
    error?: string | null;
}

/** Published portal branding (Admin → Branding); logo URLs already fall back to the header logo. */
export interface Branding {
    name: string;
    tagline: string;
    show_name: boolean;
    logos: { header: string | null; mobile: string | null; footer: string | null; login: string | null };
    favicon: string | null;
}

export interface SharedProps {
    appName: string;
    features: { ai: boolean };
    auth: { user: AuthUser | null };
    flash: Flash;
    branding: Branding;
    errors: Record<string, string>;
    [key: string]: unknown;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

export interface Option<V = string> {
    value: V;
    label: string;
}
