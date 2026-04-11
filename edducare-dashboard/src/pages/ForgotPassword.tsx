import React, { useState } from 'react';
import { GraduationCap, ArrowLeft, Mail, RefreshCw } from 'lucide-react';
import { Link } from 'react-router-dom';
import { authService } from '../api/services';
import toast from 'react-hot-toast';

const ForgotPassword: React.FC = () => {
    const [email, setEmail] = useState('');
    const [loading, setLoading] = useState(false);
    const [submitted, setSubmitted] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!email) {
            toast.error('Please enter your email address');
            return;
        }

        setLoading(true);
        try {
            const res = await authService.forgotPassword(email);
            if (res.data.success) {
                setSubmitted(true);
                toast.success('Reset link sent!');
            }
        } catch (err: any) {
            toast.error(err?.response?.data?.error || 'Failed to send reset link');
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
            {/* Background elements same as Login */}
            <div style={{
                position: 'absolute', top: '-20%', left: '-10%',
                width: 500, height: 500,
                background: 'radial-gradient(circle, rgba(99,102,241,0.1) 0%, transparent 70%)',
                borderRadius: '50%', pointerEvents: 'none',
            }} />

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
                        Reset Password
                    </h1>
                </div>

                <div className="card" style={{ padding: 32 }}>
                    {!submitted ? (
                        <>
                            <p style={{ color: 'var(--text-secondary)', marginBottom: 24, fontSize: '0.9rem', textAlign: 'center' }}>
                                Enter your email address and we'll send you a link to reset your password.
                            </p>
                            <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                                <div className="form-group">
                                    <label htmlFor="email">Email Address</label>
                                    <input
                                        id="email" className="input" type="email"
                                        placeholder="Enter your registered email"
                                        value={email} onChange={(e) => setEmail(e.target.value)}
                                        autoFocus
                                        required
                                    />
                                </div>

                                <button className="btn btn-primary btn-lg" type="submit" disabled={loading} style={{ width: '100%', justifyContent: 'center' }}>
                                    {loading ? <RefreshCw className="spin" size={20} /> : 'Send Reset Link'}
                                </button>
                            </form>
                        </>
                    ) : (
                        <div style={{ textAlign: 'center' }}>
                            <div style={{ width: 64, height: 64, borderRadius: '50%', background: 'var(--bg-elevated)', border: '2px solid var(--primary-light)', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 20px', color: 'var(--primary-light)' }}>
                                <Mail size={32} />
                            </div>
                            <h3 style={{ fontSize: '1.2rem', fontWeight: 700, color: 'var(--text-primary)', marginBottom: 12 }}>Check your email</h3>
                            <p style={{ color: 'var(--text-secondary)', fontSize: '0.9rem', lineHeight: 1.6, marginBottom: 24 }}>
                                We've sent a password reset link to <br /><strong style={{ color: 'var(--text-primary)' }}>{email}</strong>
                            </p>
                            <button onClick={() => setSubmitted(false)} className="btn btn-secondary" style={{ width: '100%', justifyContent: 'center' }}>
                                Didn't receive email? Try again
                            </button>
                        </div>
                    )}

                    <div style={{ borderTop: '1px solid var(--bg-border)', marginTop: 24, paddingTop: 20, textAlign: 'center' }}>
                        <Link to="/login" style={{ fontSize: '0.85rem', color: 'var(--text-muted)', textDecoration: 'none', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8 }}>
                            <ArrowLeft size={14} /> Back to Sign In
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default ForgotPassword;
