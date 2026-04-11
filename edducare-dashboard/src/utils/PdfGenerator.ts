/**
 * PdfGenerator.ts – Professional PDF generation for Edducare School ERP
 * 
 * Generates:
 *   1. Fee Payment Invoices
 *   2. Student Result Cards
 *   3. Certificates (Transfer, Character, Achievement)
 *   4. Reports (Attendance, Financial summaries)
 */

import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

// Extend jsPDF type to include autoTable (for internal types)
declare module 'jspdf' {
    interface jsPDF {
        // We still declare it but we'll use autoTable(doc, ...) for reliability
        lastAutoTable: { finalY: number };
    }
}

// ── Color Constants ───────────────────────────────────────────────────────────
const COLORS = {
    primary: [59, 130, 246] as [number, number, number],       // #3B82F6
    primaryDark: [30, 64, 175] as [number, number, number],    // #1E40AF
    secondary: [99, 102, 241] as [number, number, number],     // #6366F1
    success: [16, 185, 129] as [number, number, number],       // #10B981
    danger: [239, 68, 68] as [number, number, number],         // #EF4444
    warning: [245, 158, 11] as [number, number, number],       // #F59E0B
    dark: [15, 23, 42] as [number, number, number],            // #0F172A
    muted: [100, 116, 139] as [number, number, number],        // #64748B
    light: [241, 245, 249] as [number, number, number],        // #F1F5F9
    white: [255, 255, 255] as [number, number, number],
    border: [226, 232, 240] as [number, number, number],       // #E2E8F0
};

// ── Interfaces ────────────────────────────────────────────────────────────────
interface SchoolInfo {
    name: string;
    address?: string;
    phone?: string;
    email?: string;
    website?: string;
    principal_name?: string;
    logoUrl?: string;
}

interface InvoiceData {
    invoiceNumber: string;
    date: string;
    student: {
        name: string;
        studentId: string;
        class: string;
        section?: string;
        fatherName?: string;
        phone?: string;
    };
    items: Array<{
        description: string;
        amount: number;
    }>;
    totalAmount: number;
    paidAmount: number;
    paymentMethod: string;
    transactionId?: string;
    remarks?: string;
}

interface ResultData {
    student: {
        name: string;
        studentId: string;
        rollNumber?: string;
        class: string;
        section?: string;
        admissionNumber?: string;
        fatherName?: string;
        motherName?: string;
        dob?: string;
    };
    examName: string;
    examDate?: string;
    academicYear?: string;
    subjects: Array<{
        name: string;
        maxMarks: number;
        obtainedMarks: number;
        grade?: string;
    }>;
    totalMarks: number;
    totalObtained: number;
    percentage: number;
    result: 'PASS' | 'FAIL';
    rank?: number;
    remarks?: string;
}

interface CertificateData {
    type: 'transfer' | 'character' | 'achievement' | 'participation';
    student: {
        name: string;
        studentId: string;
        class: string;
        section?: string;
        fatherName?: string;
        dob?: string;
        admissionDate?: string;
    };
    issueDate: string;
    serialNumber?: string;
    reason?: string;  // For transfer certificate
    achievementTitle?: string;  // For achievement certificate
    eventName?: string;  // For participation
    conduct?: string;
    remarks?: string;
}

// ── Helper: Draw School Header ────────────────────────────────────────────────
function drawSchoolHeader(doc: jsPDF, school: SchoolInfo, docTitle: string): number {
    const pageWidth = doc.internal.pageSize.getWidth();
    
    // Top gradient bar
    doc.setFillColor(...COLORS.primary);
    doc.rect(0, 0, pageWidth, 4, 'F');
    
    // Header background (lighter gradient look)
    doc.setFillColor(248, 252, 255);
    doc.rect(0, 4, pageWidth, 44, 'F');
    
    // School Logo or Initials
    if (school.logoUrl) {
        try {
            // If it's a base64 or already a data URL, it works directly.
            // For external URLs, the calling code should ideally pre-load it.
            doc.addImage(school.logoUrl, 'PNG', 15, 12, 28, 28, undefined, 'FAST');
        } catch (e) {
            console.error("PDF Logo Error:", e);
            // Fallback to initials
            drawInitialsLogo(doc, school.name);
        }
    } else {
        drawInitialsLogo(doc, school.name);
    }
    
    // School name
    const textX = school.logoUrl ? 52 : 50;
    doc.setTextColor(...COLORS.dark);
    doc.setFontSize(18);
    doc.setFont('helvetica', 'bold');
    doc.text(school.name.toUpperCase(), textX, 22);
    
    // School details
    doc.setFontSize(8.5);
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(...COLORS.muted);
    const detailsLines: string[] = [];
    if (school.address) detailsLines.push(school.address);
    doc.text(detailsLines.join(''), textX, 29);
    
    const infraDetails: string[] = [];
    if (school.phone) infraDetails.push(`Phone: ${school.phone}`);
    if (school.email) infraDetails.push(`Email: ${school.email}`);
    if (school.website) infraDetails.push(`Web: ${school.website}`);
    doc.text(infraDetails.join('  |  '), textX, 35);
    
    // Document title on right
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(12);
    doc.setTextColor(...COLORS.primary);
    doc.text(docTitle, pageWidth - 15, 22, { align: 'right' });
    
    // Separator line
    doc.setDrawColor(...COLORS.primary);
    doc.setLineWidth(0.8);
    doc.line(15, 52, pageWidth - 15, 52);
    
    return 60; // Return Y position after header
}

function drawInitialsLogo(doc: jsPDF, schoolName: string) {
    doc.setFillColor(...COLORS.primary);
    doc.circle(30, 26, 14, 'F');
    doc.setTextColor(...COLORS.white);
    doc.setFontSize(12);
    doc.setFont('helvetica', 'bold');
    const initials = schoolName.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
    doc.text(initials, 30, 29, { align: 'center' });
}

// ── Helper: Draw Footer ──────────────────────────────────────────────────────
function drawFooter(doc: jsPDF, _school: SchoolInfo, pageNum?: number): void {
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    
    // Separator
    doc.setDrawColor(...COLORS.border);
    doc.setLineWidth(0.3);
    doc.line(15, pageHeight - 25, pageWidth - 15, pageHeight - 25);
    
    // Footer text
    doc.setFontSize(7);
    doc.setTextColor(...COLORS.muted);
    doc.setFont('helvetica', 'normal');
    doc.text('This is a computer-generated document. No signature is required.', 15, pageHeight - 18);
    doc.text(`Generated by Edducare ERP  •  ${new Date().toLocaleString()}`, 15, pageHeight - 13);
    
    if (pageNum) {
        doc.text(`Page ${pageNum}`, pageWidth - 20, pageHeight - 13, { align: 'right' });
    }
    
    // Bottom gradient bar
    doc.setFillColor(...COLORS.primary);
    doc.rect(0, pageHeight - 4, pageWidth, 4, 'F');
}

// ── Helper: Text row (label: value) ──────────────────────────────────────────
function drawLabelValue(doc: jsPDF, x: number, y: number, label: string, value: string): void {
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(8);
    doc.setTextColor(...COLORS.muted);
    doc.text(label, x, y);
    
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(9);
    doc.setTextColor(...COLORS.dark);
    doc.text(value || '—', x + 0, y + 5);
}

// ══════════════════════════════════════════════════════════════════════════════
// ═══ 1. PAYMENT INVOICE ═══════════════════════════════════════════════════════
// ══════════════════════════════════════════════════════════════════════════════
export function generatePaymentInvoice(school: SchoolInfo, data: InvoiceData): void {
    const doc = new jsPDF('p', 'mm', 'a4');
    const pageWidth = doc.internal.pageSize.getWidth();
    
    let y = drawSchoolHeader(doc, school, 'PAYMENT RECEIPT');
    
    // Invoice info row
    doc.setFillColor(248, 250, 252);
    doc.roundedRect(15, y, pageWidth - 30, 20, 3, 3, 'F');
    
    doc.setFontSize(8);
    doc.setFont('helvetica', 'bold');
    doc.setTextColor(...COLORS.primary);
    doc.text(`Receipt No: ${data.invoiceNumber}`, 20, y + 8);
    
    doc.setTextColor(...COLORS.muted);
    doc.setFont('helvetica', 'normal');
    doc.text(`Date: ${data.date}`, 20, y + 14);
    
    // Status badge
    const statusText = data.paidAmount >= data.totalAmount ? 'PAID' : 'PARTIAL';
    const statusColor = data.paidAmount >= data.totalAmount ? COLORS.success : COLORS.warning;
    doc.setFillColor(...statusColor);
    doc.roundedRect(pageWidth - 50, y + 4, 30, 12, 2, 2, 'F');
    doc.setTextColor(...COLORS.white);
    doc.setFontSize(8);
    doc.setFont('helvetica', 'bold');
    doc.text(statusText, pageWidth - 35, y + 12, { align: 'center' });
    
    y += 28;
    
    // Student details box (Redesigned)
    doc.setFillColor(252, 253, 255);
    doc.roundedRect(15, y, pageWidth - 30, 38, 3, 3, 'F');
    doc.setDrawColor(...COLORS.primary);
    doc.setLineWidth(0.1);
    doc.roundedRect(15, y, pageWidth - 30, 38, 3, 3, 'S');
    
    // Sidebar accent for the section
    doc.setFillColor(...COLORS.primary);
    doc.rect(15, y + 5, 2, 10, 'F');
    
    doc.setFontSize(9);
    doc.setFont('helvetica', 'bold');
    doc.setTextColor(...COLORS.primary);
    doc.text('STUDENT INFORMATION', 20, y + 12);
    
    const startY = y + 20;
    doc.setFontSize(8);
    
    // Column 1
    drawLabelValue(doc, 22, startY, 'Student Name', data.student.name.toUpperCase());
    drawLabelValue(doc, 22, startY + 12, "Father's Name", data.student.fatherName || 'N/A');
    
    // Column 2
    drawLabelValue(doc, 85, startY, 'Student ID', data.student.studentId);
    drawLabelValue(doc, 85, startY + 12, 'Contact Info', data.student.phone || 'N/A');
    
    // Column 3
    drawLabelValue(doc, 145, startY, 'Class & Section', `${data.student.class} ${data.student.section ? '- ' + data.student.section : ''}`);
    drawLabelValue(doc, 145, startY + 12, 'Academic Year', new Date().getFullYear().toString());
    
    y += 48;

    // Currency Symbol replacement for better compatibility (Indian Rupee Symbol often breaks in PDF)
    const currency = 'Rs.';

    
    // Fee breakdown table
    autoTable(doc, {
        startY: y,
        head: [['#', 'Fee Description', `Amount (${currency})`]],
        body: data.items.map((item, idx) => [
            (idx + 1).toString(),
            item.description,
            `${currency} ${item.amount.toLocaleString('en-IN')}`
        ]),
        foot: [
            ['', 'Total Amount', `${currency} ${data.totalAmount.toLocaleString('en-IN')}`],
            ['', 'Amount Paid', `${currency} ${data.paidAmount.toLocaleString('en-IN')}`],
            ['', 'Balance Due', `${currency} ${(data.totalAmount - data.paidAmount).toLocaleString('en-IN')}`],
        ],
        margin: { left: 15, right: 15 },
        styles: {
            fontSize: 9,
            cellPadding: 6,
            lineWidth: 0.1,
            lineColor: COLORS.border as any,
        },
        headStyles: {
            fillColor: COLORS.primary as any,
            textColor: COLORS.white as any,
            fontStyle: 'bold',
            fontSize: 8,
        },
        footStyles: {
            fillColor: [248, 250, 252],
            textColor: COLORS.dark as any,
            fontStyle: 'bold',
            fontSize: 9,
        },
        alternateRowStyles: {
            fillColor: [250, 251, 252],
        },
        columnStyles: {
            0: { halign: 'center', cellWidth: 15 },
            2: { halign: 'right', cellWidth: 40 },
        },
    });
    
    y = doc.lastAutoTable.finalY + 10;
    
    // Payment details
    doc.setFillColor(248, 250, 252);
    doc.roundedRect(15, y, pageWidth - 30, 20, 3, 3, 'F');
    
    drawLabelValue(doc, 20, y + 5, 'Payment Method', data.paymentMethod.toUpperCase());
    drawLabelValue(doc, 80, y + 5, 'Transaction ID', data.transactionId || 'N/A');
    if (data.remarks) {
        drawLabelValue(doc, 140, y + 5, 'Remarks', data.remarks);
    }
    
    y += 28;
    
    // Signature area
    doc.setDrawColor(...COLORS.border);
    doc.setLineWidth(0.3);
    doc.line(pageWidth - 75, y + 15, pageWidth - 20, y + 15);
    doc.setFontSize(8);
    doc.setTextColor(...COLORS.muted);
    doc.text('Authorized Signature', pageWidth - 47, y + 21, { align: 'center' });
    
    drawFooter(doc, school);
    
    doc.save(`Invoice_${data.invoiceNumber}_${data.student.name.replace(/\s/g, '_')}.pdf`);
}

// ══════════════════════════════════════════════════════════════════════════════
// ═══ 2. STUDENT RESULT CARD ══════════════════════════════════════════════════
// ══════════════════════════════════════════════════════════════════════════════
export function generateResultCard(school: SchoolInfo, data: ResultData): void {
    const doc = new jsPDF('p', 'mm', 'a4');
    const pageWidth = doc.internal.pageSize.getWidth();
    
    let y = drawSchoolHeader(doc, school, 'RESULT CARD');
    
    // Exam info banner
    doc.setFillColor(...COLORS.primary);
    doc.roundedRect(15, y, pageWidth - 30, 16, 3, 3, 'F');
    doc.setTextColor(...COLORS.white);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(10);
    doc.text(data.examName.toUpperCase(), pageWidth / 2, y + 7, { align: 'center' });
    doc.setFontSize(7);
    doc.setFont('helvetica', 'normal');
    const examInfo = [data.examDate, data.academicYear].filter(Boolean).join('  •  ');
    doc.text(examInfo, pageWidth / 2, y + 13, { align: 'center' });
    
    y += 22;
    
    // Student info
    doc.setFillColor(...COLORS.light);
    doc.roundedRect(15, y, pageWidth - 30, 35, 3, 3, 'F');
    doc.setDrawColor(...COLORS.border);
    doc.roundedRect(15, y, pageWidth - 30, 35, 3, 3, 'S');
    
    doc.setFontSize(8);
    doc.setFont('helvetica', 'bold');
    doc.setTextColor(...COLORS.primary);
    doc.text('STUDENT DETAILS', 20, y + 7);
    
    drawLabelValue(doc, 20, y + 12, 'Student Name', data.student.name);
    drawLabelValue(doc, 100, y + 12, 'Roll Number', data.student.rollNumber || '—');
    drawLabelValue(doc, 155, y + 12, 'Class', `${data.student.class}${data.student.section ? ' (' + data.student.section + ')' : ''}`);
    
    drawLabelValue(doc, 20, y + 23, "Father's Name", data.student.fatherName || '—');
    drawLabelValue(doc, 100, y + 23, 'Admission No.', data.student.admissionNumber || '—');
    drawLabelValue(doc, 155, y + 23, 'Date of Birth', data.student.dob || '—');
    
    y += 42;
    
    // Marks table
    const gradeFromMarks = (obtained: number, max: number): string => {
        const pct = (obtained / max) * 100;
        if (pct >= 90) return 'A+';
        if (pct >= 80) return 'A';
        if (pct >= 70) return 'B+';
        if (pct >= 60) return 'B';
        if (pct >= 50) return 'C';
        if (pct >= 33) return 'D';
        return 'F';
    };
    
    autoTable(doc, {
        startY: y,
        head: [['#', 'Subject', 'Max Marks', 'Marks Obtained', 'Grade', 'Status']],
        body: data.subjects.map((sub, idx) => {
            const grade = sub.grade || gradeFromMarks(sub.obtainedMarks, sub.maxMarks);
            const pct = (sub.obtainedMarks / sub.maxMarks) * 100;
            return [
                (idx + 1).toString(),
                sub.name,
                sub.maxMarks.toString(),
                sub.obtainedMarks.toString(),
                grade,
                pct >= 33 ? 'PASS' : 'FAIL'
            ];
        }),
        foot: [
            ['', 'TOTAL', data.totalMarks.toString(), data.totalObtained.toString(), '', `${data.percentage.toFixed(1)}%`]
        ],
        margin: { left: 15, right: 15 },
        styles: {
            fontSize: 9,
            cellPadding: 5,
            lineWidth: 0.1,
            lineColor: COLORS.border as any,
            halign: 'center',
        },
        headStyles: {
            fillColor: COLORS.dark as any,
            textColor: COLORS.white as any,
            fontStyle: 'bold',
            fontSize: 8,
        },
        footStyles: {
            fillColor: COLORS.primary as any,
            textColor: COLORS.white as any,
            fontStyle: 'bold',
            fontSize: 9,
        },
        alternateRowStyles: {
            fillColor: [250, 251, 252] as any,
        },
        columnStyles: {
            0: { cellWidth: 12 },
            1: { halign: 'left', cellWidth: 55 },
            2: { cellWidth: 25 },
            3: { cellWidth: 30 },
            4: { cellWidth: 20 },
            5: { cellWidth: 25 },
        },
        didParseCell: (data: any) => {
            if (data.section === 'body' && data.column.index === 5) {
                data.cell.styles.textColor = data.cell.raw === 'PASS' ? COLORS.success : COLORS.danger;
                data.cell.styles.fontStyle = 'bold';
            }
        },
    });
    
    y = doc.lastAutoTable.finalY + 12;
    
    // Result summary box
    const resultColor = data.result === 'PASS' ? COLORS.success : COLORS.danger;
    doc.setFillColor(...resultColor);
    doc.roundedRect(15, y, 60, 18, 3, 3, 'F');
    doc.setTextColor(...COLORS.white);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(14);
    doc.text(data.result, 45, y + 12, { align: 'center' });
    
    // Percentage and rank
    doc.setFillColor(248, 250, 252);
    doc.roundedRect(80, y, pageWidth - 95, 18, 3, 3, 'F');
    doc.setTextColor(...COLORS.dark);
    doc.setFontSize(10);
    doc.text(`Percentage: ${data.percentage.toFixed(1)}%`, 90, y + 8);
    if (data.rank) {
        doc.text(`Rank: ${data.rank}`, 90, y + 15);
    }
    if (data.remarks) {
        doc.setFontSize(8);
        doc.setTextColor(...COLORS.muted);
        doc.text(`Remarks: ${data.remarks}`, 145, y + 11);
    }
    
    y += 28;
    
    // Signature areas
    const sigY = y + 5;
    doc.setDrawColor(...COLORS.border);
    doc.setLineWidth(0.3);
    
    // Class Teacher
    doc.line(20, sigY + 15, 70, sigY + 15);
    doc.setFontSize(8);
    doc.setTextColor(...COLORS.muted);
    doc.text('Class Teacher', 45, sigY + 21, { align: 'center' });
    
    // Principal
    doc.line(pageWidth - 75, sigY + 15, pageWidth - 25, sigY + 15);
    doc.text('Principal', pageWidth - 50, sigY + 21, { align: 'center' });
    
    // Parent/Guardian
    doc.line(80, sigY + 15, 130, sigY + 15);
    doc.text('Parent/Guardian', 105, sigY + 21, { align: 'center' });
    
    drawFooter(doc, school);
    
    doc.save(`Result_${data.examName.replace(/\s/g, '_')}_${data.student.name.replace(/\s/g, '_')}.pdf`);
}

// ══════════════════════════════════════════════════════════════════════════════
// ═══ 3. CERTIFICATE ══════════════════════════════════════════════════════════
// ══════════════════════════════════════════════════════════════════════════════
export function generateCertificate(school: SchoolInfo, data: CertificateData): void {
    const doc = new jsPDF('l', 'mm', 'a4'); // Landscape
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    
    // Decorative border
    doc.setDrawColor(...COLORS.primary);
    doc.setLineWidth(2);
    doc.rect(8, 8, pageWidth - 16, pageHeight - 16, 'S');
    
    doc.setDrawColor(...COLORS.secondary);
    doc.setLineWidth(0.5);
    doc.rect(12, 12, pageWidth - 24, pageHeight - 24, 'S');
    
    // Corner decorations
    const cornerSize = 15;
    [[15, 15], [pageWidth - 15 - cornerSize, 15], [15, pageHeight - 15 - cornerSize], [pageWidth - 15 - cornerSize, pageHeight - 15 - cornerSize]].forEach(([x, y]) => {
        doc.setFillColor(...COLORS.primary);
        doc.triangle(x, y, x + cornerSize, y, x, y + cornerSize, 'F');
    });
    
    // Top accent bar
    doc.setFillColor(...COLORS.primary);
    doc.rect(30, 15, pageWidth - 60, 3, 'F');
    
    let y = 32;
    
    // School name
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(22);
    doc.setTextColor(...COLORS.dark);
    doc.text(school.name.toUpperCase(), pageWidth / 2, y, { align: 'center' });
    
    y += 8;
    if (school.address) {
        doc.setFontSize(9);
        doc.setFont('helvetica', 'normal');
        doc.setTextColor(...COLORS.muted);
        doc.text(school.address, pageWidth / 2, y, { align: 'center' });
    }
    
    y += 12;
    
    // Certificate type title
    const titles: Record<string, string> = {
        transfer: 'TRANSFER CERTIFICATE',
        character: 'CHARACTER CERTIFICATE',
        achievement: 'CERTIFICATE OF ACHIEVEMENT',
        participation: 'CERTIFICATE OF PARTICIPATION',
    };
    
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(24);
    doc.setTextColor(...COLORS.primary);
    doc.text(titles[data.type] || 'CERTIFICATE', pageWidth / 2, y, { align: 'center' });
    
    // Decorative line under title
    y += 4;
    doc.setDrawColor(...COLORS.secondary);
    doc.setLineWidth(0.8);
    const titleWidth = doc.getTextWidth(titles[data.type] || 'CERTIFICATE');
    doc.line(pageWidth / 2 - titleWidth / 2 - 10, y, pageWidth / 2 + titleWidth / 2 + 10, y);
    
    y += 10;
    
    // Serial number
    if (data.serialNumber) {
        doc.setFontSize(8);
        doc.setFont('helvetica', 'normal');
        doc.setTextColor(...COLORS.muted);
        doc.text(`Certificate No: ${data.serialNumber}`, pageWidth / 2, y, { align: 'center' });
        y += 6;
    }
    
    y += 4;
    
    // Certificate body text
    doc.setFontSize(12);
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(...COLORS.dark);
    
    let bodyText = '';
    
    switch (data.type) {
        case 'transfer':
            bodyText = `This is to certify that ${data.student.name}, child of ${data.student.fatherName || '_____________'}, ` +
                `was a student of this school in Class ${data.student.class}${data.student.section ? ' (Section ' + data.student.section + ')' : ''}. ` +
                `The student's date of birth as per school records is ${data.student.dob || '_____________'}. ` +
                `${data.student.name} has been granted this Transfer Certificate on account of ${data.reason || '_____________'}. ` +
                `The student's character and conduct during the stay at this institution were ${data.conduct || 'GOOD'}.`;
            break;
            
        case 'character':
            bodyText = `This is to certify that ${data.student.name}, child of ${data.student.fatherName || '_____________'}, ` +
                `is/was a student of Class ${data.student.class}${data.student.section ? ' (Section ' + data.student.section + ')' : ''} in this school. ` +
                `During the student's tenure, the conduct and character were found to be ${data.conduct || 'EXCELLENT'}. ` +
                `This certificate is issued at the request of the student for the purpose of further studies/employment.`;
            break;
            
        case 'achievement':
            bodyText = `This certificate is proudly awarded to ${data.student.name}, ` +
                `student of Class ${data.student.class}${data.student.section ? ' (Section ' + data.student.section + ')' : ''}, ` +
                `in recognition of outstanding achievement in "${data.achievementTitle || '_____________'}". ` +
                `This remarkable accomplishment is a testament to the student's dedication and hard work.`;
            break;
            
        case 'participation':
            bodyText = `This is to certify that ${data.student.name}, ` +
                `student of Class ${data.student.class}${data.student.section ? ' (Section ' + data.student.section + ')' : ''}, ` +
                `actively participated in "${data.eventName || '_____________'}" organized by the school. ` +
                `We appreciate the student's enthusiasm and effort.`;
            break;
    }
    
    // Split body into lines
    const lines = doc.splitTextToSize(bodyText, pageWidth - 80);
    doc.text(lines, 40, y, { lineHeightFactor: 1.8 });
    
    y = Math.max(y + lines.length * 8, pageHeight - 60);
    
    // Date and signatures
    doc.setFontSize(9);
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(...COLORS.muted);
    doc.text(`Date: ${data.issueDate}`, 40, y);
    doc.text(`Place: _______________`, 40, y + 6);
    
    // Signature lines
    doc.setDrawColor(...COLORS.border);
    doc.setLineWidth(0.3);
    doc.line(pageWidth - 90, y, pageWidth - 30, y);
    doc.setFontSize(9);
    doc.setFont('helvetica', 'bold');
    doc.setTextColor(...COLORS.dark);
    doc.text(school.principal_name || 'Principal', pageWidth - 60, y + 6, { align: 'center' });
    doc.setFontSize(8);
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(...COLORS.muted);
    doc.text('Principal / Headmaster', pageWidth - 60, y + 11, { align: 'center' });
    
    // School stamp area
    doc.setDrawColor(...COLORS.border);
    doc.setLineWidth(0.5);
    doc.circle(pageWidth / 2, y + 2, 14, 'S');
    doc.setFontSize(6);
    doc.setTextColor(...COLORS.muted);
    doc.text('SCHOOL', pageWidth / 2, y + 1, { align: 'center' });
    doc.text('STAMP', pageWidth / 2, y + 5, { align: 'center' });
    
    // Bottom accent bar
    doc.setFillColor(...COLORS.primary);
    doc.rect(30, pageHeight - 18, pageWidth - 60, 3, 'F');
    
    const certTypeName = data.type.charAt(0).toUpperCase() + data.type.slice(1);
    doc.save(`${certTypeName}_Certificate_${data.student.name.replace(/\s/g, '_')}.pdf`);
}

// ══════════════════════════════════════════════════════════════════════════════
// ═══ 4. ATTENDANCE/FINANCIAL REPORT ══════════════════════════════════════════
// ══════════════════════════════════════════════════════════════════════════════
interface ReportTableData {
    title: string;
    subtitle?: string;
    columns: string[];
    rows: string[][];
    summaryItems?: Array<{ label: string; value: string }>;
}

export function generateReportPdf(school: SchoolInfo, report: ReportTableData): void {
    const doc = new jsPDF('p', 'mm', 'a4');
    const pageWidth = doc.internal.pageSize.getWidth();
    
    let y = drawSchoolHeader(doc, school, report.title.toUpperCase());
    
    // Report title
    if (report.subtitle) {
        doc.setFontSize(9);
        doc.setFont('helvetica', 'normal');
        doc.setTextColor(...COLORS.muted);
        doc.text(report.subtitle, 15, y);
        y += 8;
    }
    
    // Summary cards
    if (report.summaryItems && report.summaryItems.length > 0) {
        const cardWidth = (pageWidth - 30 - (report.summaryItems.length - 1) * 5) / report.summaryItems.length;
        
        report.summaryItems.forEach((item, idx) => {
            const x = 15 + idx * (cardWidth + 5);
            doc.setFillColor(...COLORS.light);
            doc.roundedRect(x, y, cardWidth, 22, 2, 2, 'F');
            
            doc.setFontSize(7);
            doc.setFont('helvetica', 'normal');
            doc.setTextColor(...COLORS.muted);
            doc.text(item.label, x + 5, y + 8);
            
            doc.setFontSize(12);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(...COLORS.dark);
            doc.text(item.value, x + 5, y + 17);
        });
        
        y += 30;
    }
    
    // Data table
    autoTable(doc, {
        startY: y,
        head: [report.columns],
        body: report.rows,
        margin: { left: 15, right: 15 },
        styles: {
            fontSize: 8,
            cellPadding: 5,
            lineWidth: 0.1,
            lineColor: COLORS.border as any,
        },
        headStyles: {
            fillColor: COLORS.dark as any,
            textColor: COLORS.white as any,
            fontStyle: 'bold',
            fontSize: 7.5,
        },
        alternateRowStyles: {
            fillColor: [250, 251, 252],
        },
        didDrawPage: () => {
            drawFooter(doc, school, doc.internal.pages.length - 1);
        },
    });
    
    drawFooter(doc, school);
    
    doc.save(`${report.title.replace(/\s/g, '_')}_Report_${new Date().toISOString().split('T')[0]}.pdf`);
}

export default {
    generatePaymentInvoice,
    generateResultCard,
    generateCertificate,
    generateReportPdf,
};
