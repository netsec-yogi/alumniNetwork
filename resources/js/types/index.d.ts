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
    verification_status: 'pending' | 'verified' | 'rejected' | 'suspended' | 'archived' | null;
}

export interface Flash {
    status?: string | null;
    success?: string | null;
    warning?: string | null;
    error?: string | null;
}

export interface SharedProps {
    appName: string;
    auth: { user: AuthUser | null };
    flash: Flash;
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
