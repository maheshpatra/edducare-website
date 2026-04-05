// Image path resolver
export const resolveImagePath = (path, FILE_BASE) => {
  if (!path) return null;
  if (path.startsWith('http')) return path;
  return `${FILE_BASE}/${path}`;
};

// Dummy data for fallback
export const DUMMY_DATA = {
  heroImages: [
    'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?q=80&w=2070&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1580582932707-520aed937b7b?q=80&w=2032&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1509062522246-3755977927d7?q=80&w=2032&auto=format&fit=crop',
  ],
  aboutImage: 'https://images.unsplash.com/photo-1544717297-fa154daaf76e?q=80&w=2070&auto=format&fit=crop',
  principalImage: 'https://images.unsplash.com/photo-1560250097-0b93528c311a?q=80&w=1887&auto=format&fit=crop',
  gallery: [
    { id: 1, image_path: 'https://images.unsplash.com/photo-1577896851231-70ef18881754?q=80&w=2070&auto=format&fit=crop', caption: 'Science Lab Session', category: 'Academic' },
    { id: 2, image_path: 'https://images.unsplash.com/photo-1427504494785-3a9ca7044f45?q=80&w=2070&auto=format&fit=crop', caption: 'Student Collaboration', category: 'Campus Life' },
    { id: 3, image_path: 'https://images.unsplash.com/photo-1546410531-bb4caa1b424d?q=80&w=2071&auto=format&fit=crop', caption: 'Library Study Area', category: 'Campus Life' },
    { id: 4, image_path: 'https://images.unsplash.com/photo-1571260899304-425eee4c7efc?q=80&w=2070&auto=format&fit=crop', caption: 'Sports Day Championship', category: 'Sports' },
    { id: 5, image_path: 'https://images.unsplash.com/photo-1588072432836-e10032774350?q=80&w=2072&auto=format&fit=crop', caption: 'Art & Culture Exhibition', category: 'Events' },
    { id: 6, image_path: 'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?q=80&w=2022&auto=format&fit=crop', caption: 'Morning Assembly', category: 'Campus Life' },
    { id: 7, image_path: 'https://images.unsplash.com/photo-1509062522246-3755977927d7?q=80&w=2032&auto=format&fit=crop', caption: 'Computer Lab Innovation', category: 'Academic' },
    { id: 8, image_path: 'https://images.unsplash.com/photo-1564429238961-71acf3c48fc8?q=80&w=2070&auto=format&fit=crop', caption: 'Annual Cultural Fest', category: 'Events' },
  ],
  teachers: [
    { id: 1, first_name: 'Dr. Sarah', last_name: 'Johnson', email: 'sarah@school.com', qualification: 'Ph.D. Mathematics', experience_years: 15, teacher_type: 'HOD', profile_image: null },
    { id: 2, first_name: 'Prof. Michael', last_name: 'Chen', email: 'michael@school.com', qualification: 'M.Sc. Physics', experience_years: 12, teacher_type: 'Senior Faculty', profile_image: null },
    { id: 3, first_name: 'Ms. Emily', last_name: 'Williams', email: 'emily@school.com', qualification: 'M.A. English Literature', experience_years: 8, teacher_type: 'Faculty', profile_image: null },
    { id: 4, first_name: 'Mr. David', last_name: 'Patel', email: 'david@school.com', qualification: 'M.Sc. Computer Science', experience_years: 10, teacher_type: 'Faculty', profile_image: null },
  ],
  notices: [
    { id: 1, title: 'Annual Examination Schedule Released', content: 'The schedule for the annual examinations has been released. Students are advised to begin preparations and collect their hall tickets from the administration office.', priority: 'urgent', created_at: new Date().toISOString() },
    { id: 2, title: 'Parent-Teacher Meeting on Saturday', content: 'A parent-teacher meeting is scheduled for this Saturday from 10 AM to 1 PM. Parents are requested to attend and discuss their ward\'s academic progress.', priority: 'normal', created_at: new Date(Date.now() - 86400000).toISOString() },
    { id: 3, title: 'Holiday Notice for Republic Day', content: 'The school will remain closed on 26th January on account of Republic Day. Regular classes will resume the following Monday.', priority: 'normal', created_at: new Date(Date.now() - 172800000).toISOString() },
  ],
  classes: [
    { id: 1, name: 'Class I', grade_level: 'Primary', capacity: 40, current_students: 35, room_number: 'A-101' },
    { id: 2, name: 'Class V', grade_level: 'Primary', capacity: 45, current_students: 42, room_number: 'B-201' },
    { id: 3, name: 'Class X', grade_level: 'Secondary', capacity: 50, current_students: 48, room_number: 'C-301' },
  ],
  stats: [
    { label: 'Students', value: '1500' },
    { label: 'Teachers', value: '80' },
    { label: 'Years', value: '25' },
    { label: 'Success Rate', value: '98%' },
  ],
  events: [
    { date: '15', month: 'JAN', title: 'Annual Sports Meet', type: 'Sports' },
    { date: '28', month: 'FEB', title: 'Science Exhibition', type: 'Academic' },
    { date: '05', month: 'MAR', title: 'Cultural Fest 2025', type: 'Cultural' },
  ],
  testimonials: [
    { name: 'Rajesh Kumar', role: 'Parent', content: 'The academic support and mentorship my son received here has been life-changing. He is now more confident than ever.' },
    { name: 'Priya Sharma', role: 'Alumni Parent', content: 'This school\'s management is amazing. Everything is transparent, efficient and the results speak for themselves.' },
    { name: 'Amit Gupta', role: 'Parent', content: 'A truly modern school that doesn\'t forget cultural roots. The teacher-student ratio is ideal for personal attention.' },
  ],
};
