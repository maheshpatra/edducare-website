import React, { useState, useEffect } from 'react';
import { GraduationCap, Eye, EyeOff, CheckCircle, RefreshCw } from 'lucide-react';
import { useSearchParams, useNavigate, Link } from 'react-router-dom';
import { authService } from '../api/services';
import toast from 'react-hot-toast';

const ResetPassword: React.FC = () => {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const token = searchParams.get('token');

    const [password, setPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [showPwd, setShowPwd] = useState(false);
    const [loading, setLoading] = useState(false);
    const [success, setSuccess] = useState(false);

    useEffect(() => {
        if (!token) {
            toast.error('Invalid or missing reset token');
            navigate('/login');
        }
    }, [token, navigate]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (password.length < 8) {
            toast.error('Password must be at least 8 characters long');
            return;
        }

        if (password !== confirmPassword) {
            toast.error('Passwords do not match');
            return;
        }

        setLoading(true);
        try {
            const res = await authService.resetPassword({ token: token!, password });
            if (res.data.success) {
                setSuccess(true);
                toast.success('Password reset successful!');
                setTimeout(() => navigate('/login'), 3000);
            }
        } catch (err: any) {
            toast.error(err?.response?.data?.error || 'Failed to reset password');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div style={{
            minHeight: '100vh',
            background: 'var(--bg-base)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            padding: 20,
            position: 'relative',
            overflow: 'hidden',
        }}>
            <div className="slide-up" style={{ width: '100%', maxWidth: 420 }}>
                <div style={{ textAlign: 'center', marginBottom: 40 }}>
                    <div style={{
                        width: 64, height: 64,
                        background: 'var(--grad-primary)',
                        borderRadius: 'var(--radius-lg)',
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                        margin: '0 auto 16px',
                        boxShadow: 'var(--shadow-glow)',
                    }}>
                        <GraduationCap size={32} color="white" />
                    </div>
                    <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-primary)', letterSpacing: -1 }}>
                        Set New Password
                    </h1>
                </div>

                <div className="card" style={{ padding: 32 }}>
                    {!success ? (
                        <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                            <div className="form-group">
                                <label htmlFor="password">New Password</label>
                                <div style={{ position: 'relative' }}>
                                    <input
                                        id="password" 
                                        className="input" 
                                        type={showPwd ? 'text' : 'password'}
                                        placeholder="Minimum 8 characters"
                                        value={password} 
                                        onChange={(e) => setPassword(e.target.value)}
                                        style={{ paddingRight: 44 }}
                                        required
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPwd(!showPwd)}
                                        style={{
                                            position: 'absolute', right: 12, top: '50%', transform: 'translateY(-50%)',
                                            background: 'none', border: 'none', color: 'var(--text-muted)', cursor: 'pointer',
                                        }}
                                    >
                                        {showPwd ? <EyeOff size={16} /> : <Eye size={16} />}
                                    </button>
                                </div>
                            </div>

                            <div className="form-group">
                                <label htmlFor="confirmPassword">Confirm New Password</label>
                                <input
                                    id="confirmPassword" 
                                    className="input" 
                                    type={showPwd ? 'text' : 'password'}
                                    placeholder="Re-enter password"
                                    value={confirmPassword} 
                                    onChange={(e) => setConfirmPassword(e.target.value)}
                                    required
                                />
                            </div>

                            <button className="btn btn-primary btn-lg" type="submit" disabled={loading} style={{ width: '100%', justifyContent: 'center' }}>
                                {loading ? <RefreshCw className="spin" size={20} /> : 'Reset Password'}
                            </button>
                        </form>
                    ) : (
                        <div style={{ textAlign: 'center', padding: '20px 0' }}>
                            <div style={{ width: 64, height: 64, borderRadius: '50%', background: 'rgba(16,185,129,0.1)', color: '#10b981', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 20px' }}>
                                <CheckCircle size={32} />
                            </div>
                            <h3 style={{ fontSize: '1.25rem', fontWeight: 700, color: 'var(--text-primary)', marginBottom: 12 }}>Success!</h3>
                            <p style={{ color: 'var(--text-secondary)', fontSize: '0.9rem', marginBottom: 24 }}>
                                Your password has been successfully reset. Redirecting you to login...
                            </p>
                            <Link to="/login" className="btn btn-secondary" style={{ width: '100%', justifyContent: 'center' }}>
                                Go to Login
                            </Link>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

export default ResetPassword;
