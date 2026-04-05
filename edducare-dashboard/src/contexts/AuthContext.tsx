import React, { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { authService } from '../api/services';

// Matches the exact shape returned by /auth/login
export interface AuthUser {
    id: number;
    username: string;
    email: string;
    first_name: string;
    last_name: string;
    /** 'school_admin' | 'super_admin' | 'teacher_academic' | 'student' | … */
    role?: string;
    role_name?: string;
    school_id: number;
    school_name: string;
    user_type: 'user' | 'student';
    permissions?: string[] | Record<string, any> | null;
    profile_image?: string | null;
}

interface AuthContext {
    user: AuthUser | null;
    token: string | null;
    isLoading: boolean;
    isAuthenticated: boolean;
    login: (username: string, password: string) => Promise<void>;
    logout: () => void;
}

const AuthCtx = createContext<AuthContext | undefined>(undefined);

export const AuthProvider = ({ children }: { children: ReactNode }) => {
    const [user, setUser] = useState<AuthUser | null>(null);
    const [token, setToken] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    // Hydrate from localStorage on mount
    useEffect(() => {
        try {
            const storedToken = localStorage.getItem('auth_token');
            const storedUser = localStorage.getItem('auth_user');
            if (storedToken && storedUser) {
                setToken(storedToken);
                setUser(JSON.parse(storedUser));
            }
        } catch {
            // Corrupt storage – clear it
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const login = async (username: string, password: string) => {
        // POST /auth/login → { success, token, user }
        const res = await authService.login(username, password);
        const { token: jwt, user: userData } = res.data;

        if (!jwt || !userData) {
            throw new Error(res.data?.error ?? 'Login failed');
        }

        // Normalise role field (backend may return 'role' or 'role_name')
        const normalisedUser: AuthUser = {
            ...userData,
            role: userData.role ?? userData.role_name ?? 'school_admin',
        };

        localStorage.setItem('auth_token', jwt);
        localStorage.setItem('auth_user', JSON.stringify(normalisedUser));
        setToken(jwt);
        setUser(normalisedUser);
    };

    const logout = async () => {
        try { await authService.logout(); } catch { /* ignore */ }
        localStorage.removeItem('auth_token');
        localStorage.removeItem('auth_user');
        setToken(null);
        setUser(null);
    };

    return (
        <AuthCtx.Provider value={{ user, token, isLoading, isAuthenticated: !!token && !!user, login, logout }}>
            {children}
        </AuthCtx.Provider>
    );
};

export const useAuth = (): AuthContext => {
    const ctx = useContext(AuthCtx);
    if (!ctx) throw new Error('useAuth must be used inside AuthProvider');
    return ctx;
};
