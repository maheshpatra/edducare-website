import React, { useEffect, useState, useCallback } from 'react';
import {
    Plus, Search, BookOpen, ArrowRightLeft,
    CheckCircle, AlertCircle, Clock, MoreVertical,
    User, Book, Hash, Calendar, Layers, ExternalLink
} from 'lucide-react';
import { libraryService, studentService } from '../api/services';
import Modal from '../components/Modal';
import toast from 'react-hot-toast';
import '../timetable.css';

interface LibraryBook {
    id: number;
    title: string;
    author: string;
    category: string;
    isbn: string;
    total_copies: number;
    available_copies: number;
    issued_copies?: number;
}

interface Transaction {
    id: number;
    book_title: string;
    student_name: string;
    class_name: string;
    section_name: string;
    issue_date: string;
    due_date: string;
    return_date?: string;
    status: string;
    display_status: string;
    overdue_days: number;
    fine_amount: number;
}

const Library: React.FC = () => {
    const [tab, setTab] = useState<'books' | 'transactions'>('books');
    const [books, setBooks] = useState<LibraryBook[]>([]);
    const [transactions, setTransactions] = useState<Transaction[]>([]);
    const [students, setStudents] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');

    // Modals
    const [showAddBook, setShowAddBook] = useState(false);
    const [showIssue, setShowIssue] = useState(false);
    const [saving, setSaving] = useState(false);

    // Forms
    const [bookForm, setBookForm] = useState({
        title: '', author: '', category: 'Textbook',
        isbn: '', total_copies: '1', location: '', description: ''
    });
    const [issueForm, setIssueForm] = useState({
        book_id: '', student_id: '',
        due_date: new Date(Date.now() + 14 * 86400000).toISOString().split('T')[0]
    });

    const loadBooks = useCallback(async () => {
        setLoading(true);
        try {
            const res = await libraryService.books({ search });
            setBooks(res.data?.data || []);
        } catch (err) {
            toast.error('Failed to load books');
        } finally {
            setLoading(false);
        }
    }, [search]);

    const loadTransactions = useCallback(async () => {
        setLoading(true);
        try {
            const res = await libraryService.transactions();
            setTransactions(res.data?.data || []);
        } catch (err) {
            toast.error('Failed to load transactions');
        } finally {
            setLoading(false);
        }
    }, []);

    const loadStudents = async () => {
        try {
            const res = await studentService.list({ limit: 1000 });
            setStudents(res.data?.data || []);
        } catch (err) { }
    };

    useEffect(() => {
        if (tab === 'books') loadBooks();
        else loadTransactions();
    }, [tab, loadBooks, loadTransactions]);

    useEffect(() => { loadStudents(); }, []);

    const handleAddBook = async () => {
        if (!bookForm.title) { toast.error('Check required fields'); return; }
        setSaving(true);
        try {
            await libraryService.addBook(bookForm);
            toast.success('Book added successfully');
            setShowAddBook(false);
            loadBooks();
        } catch (err: any) {
            toast.error(err.response?.data?.error || 'Failed to add book');
        } finally { setSaving(false); }
    };

    const handleIssueBook = async () => {
        if (!issueForm.book_id || !issueForm.student_id) { toast.error('Select book and student'); return; }
        setSaving(true);
        try {
            await libraryService.issue(issueForm);
            toast.success('Book issued successfully');
            setShowIssue(false);
            loadBooks();
        } catch (err: any) {
            toast.error(err.response?.data?.error || 'Failed to issue book');
        } finally { setSaving(false); }
    };

    const handleReturnBook = async (id: number) => {
        try {
            await libraryService.return({ transaction_id: id });
            toast.success('Book returned successfully');
            loadTransactions();
        } catch (err: any) {
            toast.error(err.response?.data?.error || 'Failed to return book');
        }
    };

    const bf = (k: string, v: any) => setBookForm(p => ({ ...p, [k]: v }));
    const iff = (k: string, v: any) => setIssueForm(p => ({ ...p, [k]: v }));

    return (
        <div className="fade-in" style={{ padding: '24px' }}>
            {/* Header */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '32px' }}>
                <div>
                    <h1 style={{ fontSize: '24px', fontWeight: '900', color: 'var(--text-primary)', marginBottom: '4px' }}>Library Management</h1>
                    <p style={{ color: 'var(--text-muted)', fontSize: '14px', fontWeight: '500' }}>Manage inventory and keep track of book circulations</p>
                </div>
                <div style={{ display: 'flex', gap: '12px' }}>
                    <div style={{ background: 'var(--bg-elevated)', padding: '4px', borderRadius: '12px', display: 'flex', gap: '4px' }}>
                        <button
                            onClick={() => setTab('books')}
                            style={{
                                display: 'flex', alignItems: 'center', gap: '8px', padding: '8px 16px', borderRadius: '8px',
                                border: 'none', cursor: 'pointer', fontSize: '13px', fontWeight: '700', transition: 'all 0.2s',
                                background: tab === 'books' ? 'var(--bg-card)' : 'transparent',
                                color: tab === 'books' ? 'var(--primary-light)' : 'var(--text-muted)'
                            }}
                        >
                            <BookOpen size={16} /> Catalog
                        </button>
                        <button
                            onClick={() => setTab('transactions')}
                            style={{
                                display: 'flex', alignItems: 'center', gap: '8px', padding: '8px 16px', borderRadius: '8px',
                                border: 'none', cursor: 'pointer', fontSize: '13px', fontWeight: '700', transition: 'all 0.2s',
                                background: tab === 'transactions' ? 'var(--bg-card)' : 'transparent',
                                color: tab === 'transactions' ? 'var(--primary-light)' : 'var(--text-muted)'
                            }}
                        >
                            <ArrowRightLeft size={16} /> Transactions
                        </button>
                    </div>
                    {tab === 'books' && (
                        <button onClick={() => setShowAddBook(true)} className="btn btn-primary">
                            <Plus size={18} /> Add Book
                        </button>
                    )}
                    <button onClick={() => setShowIssue(true)} className="btn btn-secondary" style={{ background: 'var(--bg-card)', border: '1px solid var(--bg-border)' }}>
                        <Book size={18} style={{ color: 'var(--primary-light)' }} /> Issue New
                    </button>
                </div>
            </div>

            {/* Content Area */}
            {tab === 'books' ? (
                <>
                    <div style={{ marginBottom: '24px', maxWidth: '400px' }}>
                        <div className="search-bar">
                            <Search size={18} style={{ color: 'var(--text-muted)' }} />
                            <input
                                placeholder="Search by title, author, or ISBN..."
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                style={{ background: 'transparent', border: 'none', width: '100%', color: 'var(--text-primary)', outline: 'none' }}
                            />
                        </div>
                    </div>

                    {loading ? (
                        <div style={{ padding: '100px', textAlign: 'center' }}><div className="spinner" style={{ margin: 'auto' }} /></div>
                    ) : books.length === 0 ? (
                        <div className="card" style={{ padding: '60px', textAlign: 'center', color: 'var(--text-muted)' }}>
                            <BookOpen size={48} style={{ opacity: 0.2, marginBottom: '16px' }} />
                            <div style={{ fontWeight: '700' }}>No books found in catalog</div>
                        </div>
                    ) : (
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: '20px' }}>
                            {books.map(b => (
                                <div key={b.id} className="glass-card zoom-in" style={{ padding: '24px', display: 'flex', flexDirection: 'column', gap: '16px' }}>
                                    <div style={{ display: 'flex', gap: '16px' }}>
                                        <div style={{ width: '60px', height: '84px', borderRadius: '8px', background: 'var(--bg-elevated)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0, border: '1px solid var(--bg-border)' }}>
                                            <BookOpen size={30} style={{ color: 'var(--primary-light)', opacity: 0.5 }} />
                                        </div>
                                        <div style={{ flex: 1, minWidth: 0 }}>
                                            <div style={{ fontStyle: 'italic', fontSize: '10px', color: 'var(--text-muted)', fontWeight: '800', textTransform: 'uppercase', marginBottom: '2px' }}>{b.category}</div>
                                            <div style={{ fontWeight: '900', color: 'var(--text-primary)', fontSize: '16px', lineHeight: '1.2' }}>{b.title}</div>
                                            <div style={{ fontSize: '13px', color: 'var(--text-secondary)', fontWeight: '600', marginTop: '4px' }}>{b.author}</div>
                                        </div>
                                    </div>

                                    <div style={{ display: 'flex', justifyContent: 'space-between', padding: '12px 0', borderTop: '1px solid var(--bg-border)', borderBottom: '1px solid var(--bg-border)' }}>
                                        <div style={{ textAlign: 'center' }}>
                                            <div style={{ fontWeight: '900', color: 'var(--text-primary)' }}>{b.total_copies}</div>
                                            <div style={{ fontSize: '10px', fontWeight: '700', color: 'var(--text-muted)' }}>TOTAL</div>
                                        </div>
                                        <div style={{ textAlign: 'center' }}>
                                            <div style={{ fontWeight: '900', color: 'var(--success)' }}>{b.available_copies}</div>
                                            <div style={{ fontSize: '10px', fontWeight: '700', color: 'var(--text-muted)' }}>STOCK</div>
                                        </div>
                                        <div style={{ textAlign: 'center' }}>
                                            <div style={{ fontWeight: '900', color: 'var(--warning)' }}>{(b.issued_copies || 0)}</div>
                                            <div style={{ fontSize: '10px', fontWeight: '700', color: 'var(--text-muted)' }}>ISSUED</div>
                                        </div>
                                    </div>

                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                                            <Hash size={12} style={{ color: 'var(--text-muted)' }} />
                                            <span style={{ fontSize: '11px', fontWeight: '600', color: 'var(--text-muted)' }}>{b.isbn || 'No ISBN'}</span>
                                        </div>
                                        <button className={`btn ${b.available_copies > 0 ? 'btn-success' : 'btn-danger'}`} style={{ fontSize: '11px', height: '28px', padding: '0 12px', minWidth: '80px' }}
                                            onClick={() => { if (b.available_copies > 0) { iff('book_id', String(b.id)); setShowIssue(true); } }}>
                                            {b.available_copies > 0 ? 'Issue Now' : 'Out of Stock'}
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </>
            ) : (
                <div className="card" style={{ padding: 0 }}>
                    <div className="table-wrapper">
                        <table style={{ minWidth: '900px' }}>
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Book Title</th>
                                    <th>Issue Date</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Fine</th>
                                    <th style={{ textAlign: 'right' }}>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {loading ? (
                                    <tr><td colSpan={10} style={{ textAlign: 'center', padding: '80px' }}><div className="spinner" style={{ margin: 'auto' }} /></td></tr>
                                ) : transactions.length === 0 ? (
                                    <tr><td colSpan={10} style={{ textAlign: 'center', padding: '80px', color: 'var(--text-muted)' }}>No transaction history found</td></tr>
                                ) : transactions.map(t => (
                                    <tr key={t.id}>
                                        <td>
                                            <div style={{ fontWeight: '800', color: 'var(--text-primary)' }}>{t.student_name}</div>
                                            <div style={{ fontSize: '10px', fontWeight: '700', color: 'var(--text-muted)' }}>{t.class_name} • {t.section_name}</div>
                                        </td>
                                        <td style={{ fontWeight: '700', color: 'var(--primary-light)', maxWidth: '250px' }}>{t.book_title}</td>
                                        <td style={{ fontSize: '13px', fontWeight: '600' }}>{t.issue_date}</td>
                                        <td style={{ fontSize: '13px', fontWeight: '600' }}>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                                                <Calendar size={14} style={{ opacity: 0.5 }} />
                                                <span style={{ color: t.display_status === 'overdue' ? 'var(--danger)' : 'inherit' }}>{t.due_date}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span style={{
                                                padding: '4px 10px', borderRadius: '12px', fontSize: '10px', fontWeight: '900', textTransform: 'uppercase',
                                                background: t.display_status === 'returned' ? 'var(--success-glow)' : t.display_status === 'overdue' ? 'var(--danger-glow)' : 'var(--warning-glow)',
                                                color: t.display_status === 'returned' ? 'var(--success)' : t.display_status === 'overdue' ? 'var(--danger)' : 'var(--warning)',
                                                border: '1px solid currentColor'
                                            }}>
                                                {t.display_status}
                                            </span>
                                        </td>
                                        <td style={{ fontWeight: '800' }}>{t.fine_amount > 0 ? `₹${t.fine_amount}` : '—'}</td>
                                        <td style={{ textAlign: 'right' }}>
                                            {t.status === 'issued' ? (
                                                <button className="btn btn-success" style={{ padding: '0 16px', height: '32px', fontSize: '12px' }} onClick={() => handleReturnBook(t.id)}>
                                                    Mark Returned
                                                </button>
                                            ) : (
                                                <div style={{ color: 'var(--text-muted)', fontSize: '11px', fontWeight: '700' }}>Returned on {t.return_date}</div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* Modals */}
            <Modal isOpen={showAddBook} onClose={() => setShowAddBook(false)} title="Add to Inventory" maxWidth={480}
                footer={<><button className="btn btn-secondary" onClick={() => setShowAddBook(false)}>Cancel</button><button className="btn btn-primary" onClick={handleAddBook} disabled={saving}>{saving ? <div className="spinner" /> : 'Register Book'}</button></>}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                    <div className="form-group"><label style={{ fontSize: '12px', fontWeight: '800', color: 'var(--text-muted)' }}>Book Title *</label><input className="input" value={bookForm.title} onChange={e => bf('title', e.target.value)} /></div>
                    <div className="form-group"><label style={{ fontSize: '12px', fontWeight: '800', color: 'var(--text-muted)' }}>Author / Publication</label><input className="input" value={bookForm.author} onChange={e => bf('author', e.target.value)} /></div>
                    <div className="grid-2">
                        <div className="form-group"><label style={{ fontSize: '12px', fontWeight: '800', color: 'var(--text-muted)' }}>Category</label>
                            <select className="input" value={bookForm.category} onChange={e => bf('category', e.target.value)}>
                                <option>Textbook</option><option>Novel</option><option>Biography</option><option>History</option><option>Science</option>
                            </select>
                        </div>
                        <div className="form-group"><label style={{ fontSize: '12px', fontWeight: '800', color: 'var(--text-muted)' }}>Initial Stock</label><input className="input" type="number" value={bookForm.total_copies} onChange={e => bf('total_copies', e.target.value)} /></div>
                    </div>
                    <div className="form-group"><label style={{ fontSize: '12px', fontWeight: '800', color: 'var(--text-muted)' }}>ISBN Number</label><input className="input" value={bookForm.isbn} onChange={e => bf('isbn', e.target.value)} /></div>
                </div>
            </Modal>

            <Modal isOpen={showIssue} onClose={() => setShowIssue(false)} title="Issue Book Resource" maxWidth={480}
                footer={<><button className="btn btn-secondary" onClick={() => setShowIssue(false)}>Cancel</button><button className="btn btn-primary" onClick={handleIssueBook} disabled={saving}>{saving ? <div className="spinner" /> : 'Complete Issue'}</button></>}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                    <div className="form-group"><label style={{ fontSize: '12px', fontWeight: '800', color: 'var(--text-muted)' }}>Select Book Resource</label>
                        <select className="input" value={issueForm.book_id} onChange={e => iff('book_id', e.target.value)}>
                            <option value="">Choose a book...</option>
                            {books.filter(b => b.available_copies > 0).map(b => <option key={b.id} value={String(b.id)}>{b.title} ({b.available_copies} in stock)</option>)}
                        </select>
                    </div>
                    <div className="form-group"><label style={{ fontSize: '12px', fontWeight: '800', color: 'var(--text-muted)' }}>Issuing To (Student) *</label>
                        <select className="input" value={issueForm.student_id} onChange={e => iff('student_id', e.target.value)}>
                            <option value="">Search and select student...</option>
                            {students.map(s => <option key={s.id} value={String(s.id)}>{s.first_name} {s.last_name} ({s.student_id})</option>)}
                        </select>
                    </div>
                    <div className="form-group"><label style={{ fontSize: '12px', fontWeight: '800', color: 'var(--text-muted)' }}>Due Date</label><input className="input" type="date" value={issueForm.due_date} onChange={e => iff('due_date', e.target.value)} /></div>
                </div>
            </Modal>
        </div>
    );
};

export default Library;
