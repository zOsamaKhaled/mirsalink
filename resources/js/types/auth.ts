export type Role = 'supervisor' | 'agent';

export type Availability = 'available' | 'busy' | 'away';

export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    role: Role;
    availability: Availability;
    invited_at: string | null;
    deactivated_at: string | null;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    can: {
        manageTeam: boolean;
    };
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
