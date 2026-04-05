// Template Configuration System
// layout_style in school_themes table controls which template is active
// Supported values: 'modern', 'classic', 'elegant', 'bold'

export const TEMPLATES = {
  modern: {
    id: 'modern',
    name: 'Modern',
    description: 'Clean, minimal design with glassmorphism effects',
    headerStyle: 'transparent', // transparent over hero
    headerClass: 'bg-white/80 backdrop-blur-xl border-b border-slate-100',
    heroStyle: 'split', // text left, image right
    cardRadius: 'rounded-3xl',
    sectionPadding: 'py-24',
    fontHeading: "'Inter', sans-serif",
    fontBody: "'Inter', sans-serif",
    navStyle: 'pill', // pill-shaped active nav
  },
  classic: {
    id: 'classic',
    name: 'Classic',
    description: 'Traditional academic look with warm tones',
    headerStyle: 'solid',
    headerClass: 'bg-white shadow-lg border-b-4 border-primary',
    heroStyle: 'fullwidth',
    cardRadius: 'rounded-2xl',
    sectionPadding: 'py-20',
    fontHeading: "'Playfair Display', serif",
    fontBody: "'Source Sans 3', sans-serif",
    navStyle: 'underline',
  },
  elegant: {
    id: 'elegant',
    name: 'Elegant',
    description: 'Sophisticated dark-accented premium design',
    headerStyle: 'dark',
    headerClass: 'bg-slate-900 text-white',
    heroStyle: 'centered',
    cardRadius: 'rounded-[2rem]',
    sectionPadding: 'py-28',
    fontHeading: "'DM Serif Display', serif",
    fontBody: "'DM Sans', sans-serif",
    navStyle: 'glow',
  },
  bold: {
    id: 'bold',
    name: 'Bold',
    description: 'Vibrant, dynamic design with large typography',
    headerStyle: 'gradient',
    headerClass: 'bg-gradient-to-r from-primary to-secondary text-white',
    heroStyle: 'magazine',
    cardRadius: 'rounded-[2.5rem]',
    sectionPadding: 'py-32',
    fontHeading: "'Space Grotesk', sans-serif",
    fontBody: "'Space Grotesk', sans-serif",
    navStyle: 'block',
  },
};

export const getTemplate = (layoutStyle) => {
  return TEMPLATES[layoutStyle] || TEMPLATES.modern;
};
