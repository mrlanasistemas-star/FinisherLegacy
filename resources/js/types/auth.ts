export type User = {
    id: number;
    first_name: string;
    last_name: string;
    name: string;
    email: string;
    phone?: string | null;
    avatar?: string;
    status: 'pending' | 'active' | 'suspended' | 'blocked';
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type AuthProfile = {
    username: string;
    photo_url: string | null;
};

export type Auth = {
    user: User | null;
    permissions: string[];
    isSuperAdmin: boolean;
    profile: AuthProfile | null;
};

/* @chisel-passkeys */
/* @end-chisel-passkeys */
