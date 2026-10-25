/*
 * Logo directions for Mirsalink (ميرسالينك): مرسال, the messenger, + link.
 *
 * Every symbol is drawn on a 48 × 48 grid from plain geometry (strokes,
 * circles, rounded rectangles), so it survives one colour and 16 px.
 * `draw(ink, accent, uid)` returns the symbol's inner SVG; `guides()` returns
 * the construction lines shown over the large specimen.
 *
 * The two words share one letter, the l / ل, and the wordmark colours it:
 * mirsa·l·ink, ميرسا·ل·ينك.
 */

const BRAND = {
    en: { name: 'mirsalink', split: ['mirsa', 'l', 'ink'] },
    ar: { name: 'ميرسالينك', split: ['ميرسا', 'ل', 'ينك'] },
    domain: 'mirsalink.com',
};

const CONCEPTS = [
    {
        id: 'shared-m',
        title: 'الحرف المشترك',
        idea: 'حرف m بقوسين: الأول للمرسال والثاني للرابط، ويجمعهما عمود واحد، كما يجمع حرف اللام الكلمتين في الاسم.',
        ink: '#4C1D5C',
        accent: '#E8407E',
        latinFont: "'Bricolage Grotesque', sans-serif",
        latinWeight: 700,
        arabicFont: "'Noto Kufi Arabic', sans-serif",
        arabicWeight: 700,
        draw: (ink, accent) => `
            <g fill="none" stroke-width="5.5" stroke-linecap="round">
                <path stroke="${ink}" d="M8 40 V25 a8 8 0 0 1 16 0 V40"/>
                <path stroke="${accent}" d="M24 25 a8 8 0 0 1 16 0 V40"/>
            </g>`,
        guides: () => `
            <path d="M0 17 H48 M0 25 H48 M0 40 H48 M8 0 V48 M24 0 V48 M40 0 V48"/>
            <circle cx="16" cy="25" r="8"/><circle cx="32" cy="25" r="8"/>`,
    },
    {
        id: 'linked-messages',
        title: 'رسالتان متشابكتان',
        idea: 'رسالتان في شكل حلقتي سلسلة: المرسال يحمل الرسالة، والرابط يوصلها. كل حلقة تمر فوق الأخرى مرة وتحتها مرة.',
        ink: '#1D2129',
        accent: '#7B61FF',
        latinFont: "'Manrope', sans-serif",
        latinWeight: 800,
        arabicFont: "'IBM Plex Sans Arabic', sans-serif",
        arabicWeight: 700,
        draw: (ink, accent, uid) => `
            <defs><clipPath id="ml-${uid}"><rect x="0" y="0" width="24" height="48"/></clipPath></defs>
            <g fill="none" stroke-width="5">
                <rect x="4.5" y="12.5" width="26" height="14" rx="7" stroke="${ink}"/>
                <rect x="17.5" y="21.5" width="26" height="14" rx="7" stroke="${accent}"/>
                <rect x="4.5" y="12.5" width="26" height="14" rx="7" stroke="${ink}" clip-path="url(#ml-${uid})"/>
            </g>`,
        guides: () => `
            <path d="M0 12.5 H48 M0 21.5 H48 M0 26.5 H48 M0 35.5 H48 M4.5 0 V48 M17.5 0 V48 M30.5 0 V48 M43.5 0 V48"/>`,
    },
    {
        id: 'meem',
        title: 'ميم المرسال',
        idea: 'حرف الميم، أول حرف في الاسم بالعربية: حلقته رسالة، وذيله الرابط الذي يوصلها.',
        ink: '#14213D',
        accent: '#FCA311',
        latinFont: "'Sora', sans-serif",
        latinWeight: 700,
        arabicFont: "'Readex Pro', sans-serif",
        arabicWeight: 600,
        draw: (ink, accent) => `
            <circle cx="28" cy="19" r="10" fill="none" stroke="${accent}" stroke-width="6"/>
            <path d="M28 29 C19 29 15 33 15 41" fill="none" stroke="${ink}" stroke-width="6" stroke-linecap="round"/>`,
        guides: () => `
            <path d="M0 9 H48 M0 19 H48 M0 29 H48 M0 41 H48 M15 0 V48 M28 0 V48 M38 0 V48"/>
            <circle cx="28" cy="19" r="10"/>`,
    },
    {
        id: 'converge',
        title: 'الالتقاء',
        idea: 'ثلاثة خطوط تلتقي في نقطة واحدة: رسائل من كل مكان تصل إلى رابط واحد يجمعها.',
        ink: '#2A2A86',
        accent: '#FF6A4D',
        latinFont: "'Outfit', sans-serif",
        latinWeight: 700,
        arabicFont: "'Almarai', sans-serif",
        arabicWeight: 800,
        draw: (ink, accent) => `
            <g fill="none" stroke="${ink}" stroke-width="5" stroke-linecap="round">
                <path d="M7 11 C17 11 18 24 27 24"/>
                <path d="M7 24 H27"/>
                <path d="M7 37 C17 37 18 24 27 24"/>
            </g>
            <circle cx="36.5" cy="24" r="6.5" fill="${accent}"/>`,
        guides: () => `
            <path d="M0 11 H48 M0 24 H48 M0 37 H48 M7 0 V48 M27 0 V48 M36.5 0 V48"/>
            <circle cx="36.5" cy="24" r="6.5"/>`,
    },
    {
        id: 'route',
        title: 'التوجيه',
        idea: 'المرسال يبلغ مفترقًا فيأخذ الرسالة إلى الموظف المناسب، والفرع الآخر يبقى مفتوحًا.',
        ink: '#1747D4',
        accent: '#F5B301',
        latinFont: "'Space Grotesk', sans-serif",
        latinWeight: 700,
        arabicFont: "'Alexandria', sans-serif",
        arabicWeight: 700,
        draw: (ink, accent) => `
            <g fill="none" stroke="${ink}" stroke-width="5" stroke-linecap="round">
                <path d="M9 24 H18"/>
                <path d="M18 24 C25 24 24 13 31 13"/>
                <path d="M18 24 C25 24 24 35 30 35"/>
            </g>
            <circle cx="8.5" cy="24" r="4.5" fill="${ink}"/>
            <circle cx="38" cy="13" r="6" fill="${accent}"/>
            <circle cx="38" cy="35" r="4" fill="none" stroke="${ink}" stroke-width="3"/>`,
        guides: () => `
            <path d="M0 13 H48 M0 24 H48 M0 35 H48 M8.5 0 V48 M18 0 V48 M38 0 V48"/>
            <circle cx="38" cy="13" r="6"/><circle cx="38" cy="35" r="4"/>`,
    },

    /* The second round: different ideas, each from another corner of the name. */
    {
        id: 'seal',
        batch: 2,
        title: 'ختم المرسال',
        knockout: true,
        idea: 'كان المرسال يحمل الرسالة مختومة بالشمع، فلا يفتحها إلا صاحبها. الختم وعد بالأمانة، وهو ما تعد به المنصة حين تبقى البيانات على خوادمكم. وفي وسطه حرف m.',
        ink: '#7A1F2B',
        accent: '#E2A93B',
        latinFont: "'Fraunces', serif",
        latinWeight: 700,
        arabicFont: "'El Messiri', sans-serif",
        arabicWeight: 700,
        draw: (ink, accent) => `
            <path d="${scallopPath(24, 24, 19.5, 1.6, 14)}" fill="${ink}"/>
            <circle cx="24" cy="24" r="13" fill="none" stroke="${accent}" stroke-width="1.8"/>
            <path d="M17 29.5 V23.5 a3.5 3.5 0 0 1 7 0 V29.5 M24 23.5 a3.5 3.5 0 0 1 7 0 V29.5" fill="none" stroke="${accent}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>`,
        guides: () => `
            <circle cx="24" cy="24" r="21.1"/><circle cx="24" cy="24" r="17.9"/><circle cx="24" cy="24" r="13"/>
            <path d="M0 24 H48 M24 0 V48 M17 0 V48 M31 0 V48"/>`,
    },
    {
        id: 'kufic-meem',
        batch: 2,
        title: 'الميم الكوفية',
        idea: 'حرف الميم بالخط الكوفي المربع، خط العمارة والزخرفة في المنطقة. حلقته المربعة رسالة، وذيله الرابط. هندسي وثابت، ويُقرأ بالعربية من النظرة الأولى.',
        ink: '#0B3954',
        accent: '#FF6B6B',
        latinFont: "'Red Hat Display', sans-serif",
        latinWeight: 800,
        arabicFont: "'Changa', sans-serif",
        arabicWeight: 700,
        draw: (ink, accent) => `
            <path d="M24 25 H11 V41" fill="none" stroke="${ink}" stroke-width="6" stroke-linecap="square"/>
            <rect x="21" y="9" width="18" height="16" fill="none" stroke="${accent}" stroke-width="6"/>`,
        guides: () => `
            <path d="M0 9 H48 M0 25 H48 M0 41 H48 M11 0 V48 M21 0 V48 M39 0 V48"/>`,
    },
    {
        id: 'arabic-lam',
        batch: 2,
        title: 'اللام المشتركة',
        idea: 'حرف اللام الذي تشترك فيه «مرسال» و«لينك»، مكتوبًا بالعربية: عمود ينزل ثم ينحني كخطّاف ملوّن يمسك الرسالة. هو ل بالعربية وl بالإنجليزية في الاسم نفسه.',
        ink: '#102542',
        accent: '#F87060',
        latinFont: "'Plus Jakarta Sans', sans-serif",
        latinWeight: 800,
        arabicFont: "'Mada', sans-serif",
        arabicWeight: 800,
        draw: (ink, accent) => `
            <g fill="none" stroke-width="6" stroke-linecap="round">
                <path stroke="${ink}" d="M31 7 V29"/>
                <path stroke="${accent}" d="M31 29 C31 36 26 40 20.5 40 C15 40 11 36 11 30"/>
            </g>`,
        guides: () => `
            <path d="M0 7 H48 M0 29 H48 M0 40 H48 M11 0 V48 M31 0 V48"/>
            <circle cx="21" cy="30" r="10"/>`,
    },
    {
        id: 'split-capsule',
        batch: 2,
        title: 'الكبسولة',
        idea: 'رسالة على هيئة كبسولة يقسمها حرف l: نصفها مرسال ونصفها رابط، والحرف بينهما أطول منهما لأنه ما يجمعهما.',
        ink: '#23395B',
        accent: '#3FA7F5',
        latinFont: "'Lexend', sans-serif",
        latinWeight: 600,
        arabicFont: "'Rubik', sans-serif",
        arabicWeight: 600,
        draw: (ink, accent) => `
            <g fill="none" stroke="${ink}" stroke-width="5" stroke-linecap="round">
                <path d="M17 12 a12 12 0 0 0 0 24"/>
                <path d="M31 12 a12 12 0 0 1 0 24"/>
            </g>
            <path d="M24 6 V42" stroke="${accent}" stroke-width="5" stroke-linecap="round"/>`,
        guides: () => `
            <path d="M0 6 H48 M0 12 H48 M0 36 H48 M0 42 H48 M5 0 V48 M24 0 V48 M43 0 V48"/>
            <circle cx="17" cy="24" r="12"/><circle cx="31" cy="24" r="12"/>`,
    },
    {
        id: 'two-corners',
        batch: 2,
        title: 'الزاويتان',
        idea: 'زاويتان من طرفين تكمّل إحداهما الأخرى، فتصنعان إطارًا واحدًا للمحادثة، والرسالة في وسطه. العميل في زاوية والفريق في الأخرى.',
        ink: '#111827',
        accent: '#F97316',
        latinFont: "'Figtree', sans-serif",
        latinWeight: 800,
        arabicFont: "'Tajawal', sans-serif",
        arabicWeight: 800,
        draw: (ink, accent) => `
            <g fill="none" stroke-width="5.5" stroke-linecap="round">
                <path stroke="${ink}" d="M8 25 V14 a6 6 0 0 1 6 -6 H25"/>
                <path stroke="${accent}" d="M40 23 V34 a6 6 0 0 1 -6 6 H23"/>
            </g>
            <rect x="20" y="20" width="8" height="8" rx="2.5" fill="${ink}"/>`,
        guides: () => `
            <path d="M0 8 H48 M0 24 H48 M0 40 H48 M8 0 V48 M24 0 V48 M40 0 V48"/>
            <circle cx="14" cy="14" r="6"/><circle cx="34" cy="34" r="6"/>`,
    },
    {
        id: 'wing',
        batch: 2,
        title: 'الجناح',
        idea: 'ثلاث ريشات من جناح المرسال، تطول من الأسفل إلى الأعلى كأنها تنطلق. سرعة الرد في رمز واحد، دون رسم طائر كامل.',
        ink: '#3A0CA3',
        accent: '#F72585',
        latinFont: "'Urbanist', sans-serif",
        latinWeight: 800,
        arabicFont: "'Baloo Bhaijaan 2', sans-serif",
        arabicWeight: 700,
        draw: (ink, accent) => `
            <g fill="none" stroke-width="5" stroke-linecap="round">
                <path stroke="${accent}" d="M9 39 C15 24 27 12 41 9"/>
                <path stroke="${ink}" d="M16 40 C21 31 29 24 40 21"/>
                <path stroke="${ink}" d="M23 41 C27 36 32 33 39 32"/>
            </g>`,
        guides: () => `
            <path d="M0 9 H48 M0 21 H48 M0 32 H48 M0 41 H48 M9 0 V48 M41 0 V48"/>`,
    },

    /* The third round. */
    {
        id: 'monogram',
        batch: 3,
        title: 'المونوغرام',
        idea: 'حرف m يرتفع عموده الأخير فيصير l: الكلمتان في حرفين متصلين، والحرف المشترك أطول ما فيهما.',
        ink: '#0F172A',
        accent: '#E9B10A',
        latinFont: "'Syne', sans-serif",
        latinWeight: 700,
        arabicFont: "'Kufam', sans-serif",
        arabicWeight: 700,
        draw: (ink, accent) => `
            <g fill="none" stroke-width="5.5" stroke-linecap="round">
                <path stroke="${ink}" d="M10 40 V27 a7 7 0 0 1 14 0 V40 M24 27 a7 7 0 0 1 14 0"/>
                <path stroke="${accent}" d="M38 7 V40"/>
            </g>`,
        guides: () => `
            <path d="M0 7 H48 M0 20 H48 M0 27 H48 M0 40 H48 M10 0 V48 M24 0 V48 M38 0 V48"/>
            <circle cx="17" cy="27" r="7"/><circle cx="31" cy="27" r="7"/>`,
    },
    {
        id: 'handover',
        batch: 3,
        title: 'التسليم',
        idea: 'عصا تُسلَّم في سباق التتابع: الروبوت يبدأ المحادثة ويسلّمها للموظف في اللحظة المناسبة، دون أن تسقط.',
        ink: '#1E3A8A',
        accent: '#FB7185',
        latinFont: "'Archivo', sans-serif",
        latinWeight: 800,
        arabicFont: "'Lalezar', sans-serif",
        arabicWeight: 400,
        draw: (ink, accent) => `
            <g fill="none" stroke-width="8" stroke-linecap="round" transform="rotate(-35 24 24)">
                <path stroke="${ink}" d="M6 19 H29"/>
                <path stroke="${accent}" d="M19 29 H42"/>
            </g>`,
        guides: () => `
            <g transform="rotate(-35 24 24)"><path d="M-10 19 H58 M-10 29 H58 M6 -10 V58 M19 -10 V58 M29 -10 V58 M42 -10 V58"/></g>`,
    },
    {
        id: 'bubble-m',
        batch: 3,
        title: 'فقاعتان تصنعان M',
        idea: 'فقاعتا محادثة متقابلتان، لكل منهما ذيل إلى جهة صاحبها، فيتكوّن منهما حرف M: العميل والفريق في حرف واحد.',
        ink: '#1F2937',
        accent: '#38BDF8',
        latinFont: "'Onest', sans-serif",
        latinWeight: 700,
        arabicFont: "'Harmattan', sans-serif",
        arabicWeight: 700,
        draw: (ink, accent) => `
            <path fill="${ink}" d="M12 8 H16 A7 7 0 0 1 23 15 V25 A7 7 0 0 1 16 32 H13 L5 40 V15 A7 7 0 0 1 12 8 Z"/>
            <path fill="${accent}" d="M36 8 H32 A7 7 0 0 0 25 15 V25 A7 7 0 0 0 32 32 H35 L43 40 V15 A7 7 0 0 0 36 8 Z"/>`,
        guides: () => `
            <path d="M0 8 H48 M0 32 H48 M0 40 H48 M5 0 V48 M23 0 V48 M25 0 V48 M43 0 V48"/>`,
    },
    {
        id: 'closed-ring',
        batch: 3,
        title: 'الحلقة المكتملة',
        idea: 'حلقة فيها فجوة واحدة، والرسالة هي التي تملؤها. كل محادثة لا تكتمل إلا بالرد.',
        ink: '#4338CA',
        accent: '#F59E0B',
        latinFont: "'Geologica', sans-serif",
        latinWeight: 700,
        arabicFont: "'Markazi Text', serif",
        arabicWeight: 700,
        draw: (ink, accent) => `
            <path d="M37.79 21.57 A14 14 0 1 1 26.43 10.21" fill="none" stroke="${ink}" stroke-width="6" stroke-linecap="round"/>
            <circle cx="33.9" cy="14.1" r="3.6" fill="${accent}"/>`,
        guides: () => `
            <circle cx="24" cy="24" r="14"/><circle cx="24" cy="24" r="17"/><circle cx="24" cy="24" r="11"/>
            <path d="M0 24 H48 M24 0 V48 M4 44 L44 4"/>`,
    },
    {
        id: 'sorting',
        batch: 3,
        title: 'الفرز',
        idea: 'ثلاث محادثات مصفوفة، والوسطى تخرج من الصف لأن الروبوت فهمها ووجّهها. الفرز هو أكثر ما يفعله الفريق كل يوم.',
        ink: '#155E75',
        accent: '#F43F5E',
        latinFont: "'Instrument Sans', sans-serif",
        latinWeight: 700,
        arabicFont: "'Vazirmatn', sans-serif",
        arabicWeight: 800,
        draw: (ink, accent) => `
            <g fill="none" stroke-width="6" stroke-linecap="round">
                <path stroke="${ink}" d="M8 13 H29 M8 35 H29"/>
                <path stroke="${accent}" d="M19 24 H40"/>
            </g>`,
        guides: () => `
            <path d="M0 13 H48 M0 24 H48 M0 35 H48 M8 0 V48 M19 0 V48 M29 0 V48 M40 0 V48"/>`,
    },
    {
        id: 'orbit',
        batch: 3,
        title: 'المدار',
        idea: 'كوكب وحوله مدار: الفريق في المركز، والمحادثات تدور حوله ولا تفلت منه.',
        ink: '#3B0764',
        accent: '#C084FC',
        latinFont: "'Albert Sans', sans-serif",
        latinWeight: 800,
        arabicFont: "'Aref Ruqaa', serif",
        arabicWeight: 700,
        draw: (ink, accent, uid) => `
            <defs><clipPath id="orbit-${uid}"><rect x="-10" y="-10" width="68" height="34" transform="rotate(-25 24 24)"/></clipPath></defs>
            <circle cx="24" cy="24" r="10" fill="${ink}"/>
            <ellipse cx="24" cy="24" rx="20" ry="7" fill="none" stroke="${accent}" stroke-width="3.5" transform="rotate(-25 24 24)"/>
            <circle cx="24" cy="24" r="10" fill="${ink}" clip-path="url(#orbit-${uid})"/>`,
        guides: () => `
            <circle cx="24" cy="24" r="10"/>
            <g transform="rotate(-25 24 24)"><path d="M-10 24 H58 M24 -10 V58"/></g>`,
    },
    {
        id: 'key',
        batch: 3,
        title: 'المفتاح',
        idea: 'مفتاح رأسه حلقة رسالة: بيانات عملائكم ومحادثاتكم على خوادمكم، ومفتاحها في يدكم وحدكم.',
        ink: '#292524',
        accent: '#D4A017',
        latinFont: "'Chivo', sans-serif",
        latinWeight: 800,
        arabicFont: "'Amiri', serif",
        arabicWeight: 700,
        draw: (ink, accent) => `
            <circle cx="15" cy="24" r="8" fill="none" stroke="${accent}" stroke-width="5.5"/>
            <path d="M23 24 H41 M34 24 V31 M41 24 V31" fill="none" stroke="${ink}" stroke-width="5.5" stroke-linecap="round"/>`,
        guides: () => `
            <circle cx="15" cy="24" r="8"/>
            <path d="M0 24 H48 M0 31 H48 M7 0 V48 M23 0 V48 M34 0 V48 M41 0 V48"/>`,
    },
    /* The fourth round. */
    {
        id: 'hoopoe',
        batch: 4,
        title: 'الهدهد',
        idea: 'الهدهد أشهر مرسال في الثقافة العربية، حمل الرسالة وعاد بالخبر. رأسه وتاجه وحدهما يكفيان ليُعرف، دون رسم طائر كامل.',
        ink: '#2B1D14',
        accent: '#E07A2F',
        latinFont: "'Gabarito', sans-serif",
        latinWeight: 800,
        arabicFont: "'Marhey', sans-serif",
        arabicWeight: 700,
        draw: (ink, accent) => {
            const feathers = [-172, -156, -140, -124, -108, -92].map((degrees) => {
                const angle = (degrees * Math.PI) / 180;
                const point = (r) => `${(21 + r * Math.cos(angle)).toFixed(2)} ${(28 + r * Math.sin(angle)).toFixed(2)}`;

                return `M${point(8)} L${point(19)}`;
            });

            return `
            <path d="${feathers.join(' ')}" fill="none" stroke="${accent}" stroke-width="3.4" stroke-linecap="round"/>
            <circle cx="21" cy="28" r="9" fill="${ink}"/>
            <path d="M29.5 30 C35 31 39.5 34 42.5 38.5" fill="none" stroke="${ink}" stroke-width="3" stroke-linecap="round"/>`;
        },
        guides: () => `
            <circle cx="21" cy="28" r="9"/><circle cx="21" cy="28" r="19"/>
            <path d="M0 9 H48 M0 28 H48 M0 38.5 H48 M2 0 V48 M21 0 V48 M42.5 0 V48"/>`,
    },
    {
        id: 'waw',
        batch: 4,
        title: 'الواو',
        idea: 'الواو في العربية حرف العطف، الحرف الذي يربط كلمة بكلمة. هو «لينك» اللغة العربية نفسها: رأسه رسالة، وذيله يمتد ليصل.',
        ink: '#0D3B66',
        accent: '#E8B931',
        latinFont: "'Jost', sans-serif",
        latinWeight: 600,
        arabicFont: "'Scheherazade New', serif",
        arabicWeight: 700,
        draw: (ink, accent) => `
            <path d="M37 17 C37 32 27 40 11 41" fill="none" stroke="${ink}" stroke-width="6" stroke-linecap="round"/>
            <circle cx="29" cy="17" r="8" fill="none" stroke="${accent}" stroke-width="6"/>`,
        guides: () => `
            <circle cx="29" cy="17" r="8"/>
            <path d="M0 9 H48 M0 17 H48 M0 41 H48 M11 0 V48 M29 0 V48 M37 0 V48"/>`,
    },
    {
        id: 'nib',
        batch: 4,
        title: 'سنّ القلم',
        idea: 'الاسم ينتهي بكلمة ink، أي الحبر. سنّ قلم الحبر هو ما كتب به المرسال رسائله، وفي وسطه ثقبه وشقّه كما في القلم الحقيقي.',
        ink: '#1C1C1C',
        accent: '#2563EB',
        knockout: true,
        latinFont: "'Young Serif', serif",
        latinWeight: 400,
        arabicFont: "'Noto Naskh Arabic', serif",
        arabicWeight: 700,
        draw: (ink, accent) => `
            <path d="M15 6 H33 V18 C33 26 28 33 24 44 C20 33 15 26 15 18 Z" fill="${ink}" stroke="${ink}" stroke-width="1" stroke-linejoin="round"/>
            <circle cx="24" cy="20" r="3.5" fill="${accent}"/>
            <path d="M24 23 V40" stroke="${accent}" stroke-width="1.8" stroke-linecap="round"/>`,
        guides: () => `
            <path d="M0 6 H48 M0 18 H48 M0 20 H48 M0 44 H48 M15 0 V48 M24 0 V48 M33 0 V48"/>
            <circle cx="24" cy="20" r="3.5"/>`,
    },
    {
        id: 'voice-m',
        batch: 4,
        title: 'الرسالة الصوتية',
        idea: 'أعمدة رسالة صوتية في واتساب، أطوالها ترسم قمّتي حرف M. كثير من العملاء يرسلون صوتًا لا نصًّا، والمنصة تسمعه وتفهمه.',
        ink: '#1B1B3A',
        accent: '#00A6D6',
        latinFont: "'Sen', sans-serif",
        latinWeight: 800,
        arabicFont: "'Lemonada', sans-serif",
        arabicWeight: 700,
        draw: (ink, accent) => `
            <g fill="none" stroke-width="5" stroke-linecap="round">
                <path stroke="${ink}" d="M8 19 V31 M16 9 V39 M32 9 V39 M40 19 V31"/>
                <path stroke="${accent}" d="M24 16 V32"/>
            </g>`,
        guides: () => `
            <path d="M0 9 H48 M0 16 H48 M0 24 H48 M0 32 H48 M0 39 H48 M8 0 V48 M16 0 V48 M24 0 V48 M32 0 V48 M40 0 V48"/>`,
    },
    {
        id: 'dot-matrix',
        batch: 4,
        title: 'النقاط',
        idea: 'حرف m مرسوم بنقاط مثل شاشة عرض رقمية: كل نقطة رسالة، ومن الرسائل الكثيرة يتكوّن الاسم. والعمود الأخير ملوّن لأنه الرابط.',
        ink: '#222222',
        accent: '#FF3B30',
        latinFont: "'Space Mono', monospace",
        latinWeight: 700,
        arabicFont: "'Rakkas', serif",
        arabicWeight: 400,
        draw: (ink, accent) => {
            const dots = [
                [0, 0], [1, 0], [2, 0], [3, 0],
                [0, 1], [2, 1], [0, 2], [2, 2], [0, 3], [2, 3],
            ];
            const linked = [[4, 1], [4, 2], [4, 3]];
            const dot = ([col, row], fill) => `<circle cx="${8 + col * 8}" cy="${12 + row * 8}" r="3" fill="${fill}"/>`;

            return dots.map((d) => dot(d, ink)).join('') + linked.map((d) => dot(d, accent)).join('');
        },
        guides: () => `
            <path d="M0 12 H48 M0 20 H48 M0 28 H48 M0 36 H48 M8 0 V48 M16 0 V48 M24 0 V48 M32 0 V48 M40 0 V48"/>`,
    },
    {
        id: 'bridge',
        batch: 4,
        title: 'الجسر',
        idea: 'جسر بين ضفتين: العميل على ضفة والفريق على الأخرى، والرسالة في أعلى القوس في طريقها إليهما.',
        ink: '#33415C',
        accent: '#E76F51',
        latinFont: "'Hanken Grotesk', sans-serif",
        latinWeight: 800,
        arabicFont: "'Mirza', serif",
        arabicWeight: 700,
        draw: (ink, accent) => `
            <path d="M9 34 A15 15 0 0 1 39 34" fill="none" stroke="${ink}" stroke-width="4"/>
            <circle cx="9" cy="34" r="5" fill="${ink}"/>
            <circle cx="39" cy="34" r="5" fill="${ink}"/>
            <circle cx="24" cy="19" r="5" fill="${accent}"/>`,
        guides: () => `
            <circle cx="24" cy="34" r="15"/>
            <path d="M0 19 H48 M0 34 H48 M9 0 V48 M24 0 V48 M39 0 V48"/>`,
    },
    {
        id: 'tag',
        batch: 4,
        title: 'الوسم',
        idea: 'بطاقة وسم معلّقة: الروبوت يقرأ كل محادثة ويضع عليها وسمها (عميل محتمل، دعم فني، صاحب شركة) قبل أن يوجّهها.',
        ink: '#5A189A',
        accent: '#FFB703',
        latinFont: "'Epilogue', sans-serif",
        latinWeight: 800,
        arabicFont: "'Ruwudu', serif",
        arabicWeight: 700,
        draw: (ink, accent) => `
            <g transform="rotate(-30 24 24)">
                <path d="M12 14 H29 L40 24 L29 34 H12 A4 4 0 0 1 8 30 V18 A4 4 0 0 1 12 14 Z" fill="none" stroke="${ink}" stroke-width="5" stroke-linejoin="round"/>
                <circle cx="29.5" cy="24" r="3.2" fill="${accent}"/>
            </g>`,
        guides: () => `
            <g transform="rotate(-30 24 24)"><path d="M-10 14 H58 M-10 24 H58 M-10 34 H58 M8 -10 V58 M29 -10 V58 M40 -10 V58"/></g>`,
    },
    /* The fifth round. */
    {
        id: 'mashrabiya',
        batch: 5,
        title: 'المشربية',
        idea: 'نافذة الروشان الحجازية: من خلف شبكها يتحدث أهل البيت ويرون دون أن يُروا. المحادثة مفتوحة، والخصوصية محفوظة.',
        ink: '#5C3A21',
        accent: '#3A86C8',
        latinFont: "'Commissioner', sans-serif",
        latinWeight: 800,
        arabicFont: "'Reem Kufi Fun', sans-serif",
        arabicWeight: 700,
        draw: (ink, accent, uid) => {
            const frame = 'M11 42 V20 a13 13 0 0 1 26 0 V42 Z';
            const lattice = Array.from({ length: 9 }, (_, i) => {
                const offset = -16 + i * 6;

                return `M${offset} 44 L${offset + 40} 4 M${offset} 4 L${offset + 40} 44`;
            }).join(' ');

            return `
            <defs><clipPath id="mash-${uid}"><path d="${frame}"/></clipPath></defs>
            <path d="${lattice}" stroke="${accent}" stroke-width="2" clip-path="url(#mash-${uid})"/>
            <path d="${frame}" fill="none" stroke="${ink}" stroke-width="4.5" stroke-linejoin="round"/>`;
        },
        guides: () => `
            <circle cx="24" cy="20" r="13"/>
            <path d="M0 7 H48 M0 20 H48 M0 42 H48 M11 0 V48 M24 0 V48 M37 0 V48"/>`,
    },
    {
        id: 'finjan',
        batch: 5,
        title: 'الفنجان',
        idea: 'فنجان القهوة يُقدَّم لكل ضيف قبل الحديث. كل عميل يراسلكم ضيف، والبخار المتصاعد محادثة بدأت للتو.',
        ink: '#3E2723',
        accent: '#F4A261',
        latinFont: "'Nunito', sans-serif",
        latinWeight: 800,
        arabicFont: "'Lateef', serif",
        arabicWeight: 700,
        draw: (ink, accent) => `
            <path d="M20 21 C17 17 23 14 20 9 M28 21 C25 17 31 14 28 9" fill="none" stroke="${accent}" stroke-width="3.5" stroke-linecap="round"/>
            <path d="M11 25 H37 L33.6 38.6 A4 4 0 0 1 29.7 41.5 H18.3 A4 4 0 0 1 14.4 38.6 Z" fill="${ink}" stroke="${ink}" stroke-width="1" stroke-linejoin="round"/>`,
        guides: () => `
            <path d="M0 9 H48 M0 21 H48 M0 25 H48 M0 41.5 H48 M11 0 V48 M20 0 V48 M28 0 V48 M37 0 V48"/>`,
    },
    {
        id: 'sadu',
        batch: 5,
        title: 'السدو',
        idea: 'حرف M بزوايا حادة على طريقة نقش السدو، نسيج البادية الذي تحفظه المملكة تراثًا. والمعيّن تحته عقدة النسيج التي تربط الخيوط.',
        ink: '#1A1A1A',
        accent: '#B91C1C',
        latinFont: "'Rethink Sans', sans-serif",
        latinWeight: 800,
        arabicFont: "'Kufam', sans-serif",
        arabicWeight: 700,
        draw: (ink, accent) => `
            <path d="M8 34 V10 L24 26 L40 10 V34" fill="none" stroke="${ink}" stroke-width="6" stroke-linejoin="miter" stroke-linecap="square"/>
            <path d="M24 31 L29 36.5 L24 42 L19 36.5 Z" fill="${accent}"/>`,
        guides: () => `
            <path d="M0 10 H48 M0 26 H48 M0 34 H48 M0 42 H48 M8 0 V48 M24 0 V48 M40 0 V48 M8 10 L24 26 L40 10"/>`,
    },
    {
        id: 'ribbon',
        batch: 5,
        title: 'الشريط المطوي',
        idea: 'شريط واحد يُطوى فيصير M، وجهه بلون وظهره بلون آخر. رسالة واحدة تمر بأيدٍ كثيرة ولا تنقطع.',
        ink: '#6A040F',
        accent: '#F48C06',
        latinFont: "'Dela Gothic One', sans-serif",
        latinWeight: 400,
        arabicFont: "'Jomhuria', serif",
        arabicWeight: 400,
        draw: (ink, accent) => `
            <path d="M7 10 H15 V40 H7 Z" fill="${ink}"/>
            <path d="M7 10 H15 L28 32 H20 Z" fill="${accent}"/>
            <path d="M20 32 H28 L41 10 H33 Z" fill="${ink}"/>
            <path d="M33 10 H41 V40 H33 Z" fill="${accent}"/>`,
        guides: () => `
            <path d="M0 10 H48 M0 32 H48 M0 40 H48 M7 0 V48 M15 0 V48 M24 0 V48 M33 0 V48 M41 0 V48"/>`,
    },
    {
        id: 'signature',
        batch: 5,
        title: 'التوقيع',
        idea: 'الحرفان m وl بخط اليد في جرّة قلم واحدة، كتوقيع آخر الرسالة. خلف الروبوت دائمًا إنسان يوقّع.',
        ink: '#1D3557',
        accent: '#E63946',
        latinFont: "'Caveat', cursive",
        latinWeight: 700,
        arabicFont: "'Aref Ruqaa', serif",
        arabicWeight: 700,
        draw: (ink, accent) => `
            <path d="M5 35 C8 30 10 24 13 24 C16 24 15 30 14 35 C16 29 19 24 22 24 C25 24 24 30 23 35 C25 29 28 24 31 24 C34 24 33 31 33 34 C34 38 37 37 39 33 C42 27 43 18 42 12 C41 7 37 8 37 13 C37 21 38 31 42 36" fill="none" stroke="${ink}" stroke-width="3.6" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="44.5" cy="38.5" r="2.6" fill="${accent}"/>`,
        guides: () => `
            <path d="M0 12 H48 M0 24 H48 M0 35 H48 M5 0 V48 M42 0 V48"/>`,
    },
    {
        id: 'mailbox',
        batch: 5,
        title: 'صندوق البريد',
        idea: 'صندوق بريد رفع رايته: وصلت رسالة جديدة. أبسط علامة يفهمها كل أحد لمعنى «عندك وارد».',
        ink: '#023E8A',
        accent: '#FF006E',
        latinFont: "'Golos Text', sans-serif",
        latinWeight: 700,
        arabicFont: "'Katibeh', serif",
        arabicWeight: 400,
        draw: (ink, accent) => `
            <path d="M22 34 V43" stroke="${ink}" stroke-width="4.5" stroke-linecap="round"/>
            <path d="M8 34 V22 A8 8 0 0 1 16 14 H32 A8 8 0 0 1 40 22 V34 Z" fill="${ink}"/>
            <path d="M34 26 V7" stroke="${accent}" stroke-width="3" stroke-linecap="round"/>
            <rect x="34" y="6" width="9" height="7" rx="1.5" fill="${accent}"/>`,
        guides: () => `
            <path d="M0 6 H48 M0 14 H48 M0 22 H48 M0 34 H48 M8 0 V48 M22 0 V48 M34 0 V48 M40 0 V48"/>`,
    },
    {
        id: 'lighthouse',
        batch: 5,
        title: 'الفنار',
        idea: 'الفنار يهدي السفن إلى المرسى، والمنصة تهدي كل رسالة إلى من يرد عليها. وبين «مرسال» و«مرسى» قرابة في اللفظ.',
        ink: '#003049',
        accent: '#FCBF49',
        latinFont: "'Funnel Display', sans-serif",
        latinWeight: 700,
        arabicFont: "'Handjet', sans-serif",
        arabicWeight: 700,
        draw: (ink, accent) => `
            <path d="M16 15 L8 12 M32 15 L40 12 M16 19 L9 21 M32 19 L39 21" stroke="${accent}" stroke-width="3" stroke-linecap="round"/>
            <path d="M18.5 12 L24 6.5 L29.5 12 Z" fill="${ink}" stroke="${ink}" stroke-width="2" stroke-linejoin="round"/>
            <rect x="20" y="13.5" width="8" height="7" rx="1" fill="${accent}"/>
            <path d="M20.5 22 H27.5 L30.5 42 H17.5 Z" fill="${ink}" stroke="${ink}" stroke-width="1" stroke-linejoin="round"/>`,
        guides: () => `
            <path d="M0 6.5 H48 M0 13.5 H48 M0 22 H48 M0 42 H48 M8 0 V48 M24 0 V48 M40 0 V48"/>`,
    },
    /* The sixth round. */
    {
        id: 'najdi',
        batch: 6,
        title: 'الشرفات النجدية',
        idea: 'شرفتان متدرّجتان من أسوار الدرعية وبيوت نجد الطينية، ترسمان معًا قمّتي M فوق جدار واحد. منصة تُبنى هنا، بطابع المكان.',
        ink: '#6B4226',
        accent: '#C8553D',
        latinFont: "'Montserrat Alternates', sans-serif",
        latinWeight: 700,
        arabicFont: "'Alexandria', sans-serif",
        arabicWeight: 700,
        draw: (ink, accent) => {
            const peak = (x) => `M${x} 35 V28 H${x + 3} V21 H${x + 6} V14 H${x + 10} V21 H${x + 13} V28 H${x + 16} V35 Z`;

            return `
            <path d="${peak(7)}" fill="${ink}"/>
            <path d="${peak(25)}" fill="${accent}"/>
            <rect x="6" y="36.5" width="36" height="6" rx="1" fill="${ink}"/>`;
        },
        guides: () => `
            <path d="M0 14 H48 M0 21 H48 M0 28 H48 M0 35 H48 M0 42.5 H48 M7 0 V48 M15 0 V48 M24 0 V48 M33 0 V48 M41 0 V48"/>`,
    },
    {
        id: 'pearl',
        batch: 6,
        title: 'اللؤلؤة',
        idea: 'محارة انفتحت عن لؤلؤة، من تراث الغوص في الخليج. والمحارة المفتوحة تشبه فمًا يتكلم: كل محادثة قد تخبئ عميلًا ثمينًا.',
        ink: '#0A2342',
        accent: '#F2A65A',
        latinFont: "'Gloock', serif",
        latinWeight: 400,
        arabicFont: "'El Messiri', sans-serif",
        arabicWeight: 700,
        draw: (ink, accent) => `
            <path d="M7 30 H41 A17 11 0 0 1 7 30 Z" fill="${ink}"/>
            <path d="M7 27 C9 15 22 5 38 5 C27 10 15 17 7 27 Z" fill="${ink}"/>
            <circle cx="26" cy="24" r="5.5" fill="${accent}"/>`,
        guides: () => `
            <circle cx="26" cy="24" r="5.5"/>
            <path d="M0 5 H48 M0 24 H48 M0 30 H48 M0 41 H48 M7 0 V48 M26 0 V48 M41 0 V48"/>`,
    },
    {
        id: 'mail-slot',
        batch: 6,
        title: 'فتحة الرسائل',
        idea: 'فتحة الرسائل في الباب، والرسالة تدخل منها. لا يحتاج المرسال أن يطرق: الرسالة تصل، والباب يبقى مغلقًا على أهله.',
        ink: '#0F4C81',
        accent: '#FFB627',
        latinFont: "'Inter Tight', sans-serif",
        latinWeight: 800,
        arabicFont: "'IBM Plex Sans Arabic', sans-serif",
        arabicWeight: 700,
        draw: (ink, accent, uid) => `
            <defs><mask id="slot-${uid}"><rect width="48" height="48" fill="#fff"/><rect x="12" y="22" width="24" height="5" rx="2.5" fill="#000"/></mask></defs>
            <rect x="6" y="14" width="36" height="28" rx="9" fill="${ink}" mask="url(#slot-${uid})"/>
            <rect x="16" y="4" width="16" height="20.5" rx="2" fill="${accent}" transform="rotate(-8 24 24.5)"/>`,
        guides: () => `
            <path d="M0 5 H48 M0 12 H48 M0 22 H48 M0 27 H48 M0 42 H48 M6 0 V48 M13 0 V48 M17 0 V48 M31 0 V48 M35 0 V48 M42 0 V48"/>`,
    },
    {
        id: 'team',
        batch: 6,
        title: 'الفريق',
        idea: 'شخصان متجاوران، كتفاهما يصنعان قوسي حرف m. الفريق يعمل على صندوق واحد، وكل واحد يكمل الآخر.',
        ink: '#2E1A47',
        accent: '#FF7F50',
        latinFont: "'Baloo 2', sans-serif",
        latinWeight: 800,
        arabicFont: "'Baloo Bhaijaan 2', sans-serif",
        arabicWeight: 700,
        draw: (ink, accent) => `
            <g fill="none" stroke-width="5.5" stroke-linecap="round">
                <path stroke="${ink}" d="M8 41 V32 a8 8 0 0 1 16 0 V41"/>
                <path stroke="${accent}" d="M24 32 a8 8 0 0 1 16 0 V41"/>
            </g>
            <circle cx="16" cy="13" r="5" fill="${ink}"/>
            <circle cx="32" cy="13" r="5" fill="${accent}"/>`,
        guides: () => `
            <circle cx="16" cy="32" r="8"/><circle cx="32" cy="32" r="8"/>
            <path d="M0 8 H48 M0 13 H48 M0 24 H48 M0 41 H48 M8 0 V48 M16 0 V48 M24 0 V48 M32 0 V48 M40 0 V48"/>`,
    },
    {
        id: 'braid',
        batch: 6,
        title: 'الضفيرة',
        idea: 'خيطان يتضافران فيصيران حبلًا واحدًا، كل خيط يمر فوق الآخر مرة وتحته مرة. العميل والفريق، والرسالة والرد.',
        ink: '#3C096C',
        accent: '#FF9E00',
        latinFont: "'Righteous', sans-serif",
        latinWeight: 400,
        arabicFont: "'Changa', sans-serif",
        arabicWeight: 700,
        draw: (ink, accent, uid) => {
            const first = 'M4 17 C10 17 10 31 16 31 S22 17 28 17 S34 31 40 31 S44 24 44 24';
            const second = 'M4 31 C10 31 10 17 16 17 S22 31 28 31 S34 17 40 17 S44 24 44 24';

            return `
            <defs><clipPath id="braid-${uid}"><rect x="16" y="0" width="12" height="48"/></clipPath></defs>
            <g fill="none" stroke-width="5" stroke-linecap="round">
                <path stroke="${ink}" d="${first}"/>
                <path stroke="${accent}" d="${second}"/>
                <path stroke="${ink}" d="${first}" clip-path="url(#braid-${uid})"/>
            </g>`;
        },
        guides: () => `
            <path d="M0 17 H48 M0 24 H48 M0 31 H48 M4 0 V48 M10 0 V48 M22 0 V48 M34 0 V48 M44 0 V48"/>`,
    },
    {
        id: 'hooked-m',
        batch: 6,
        title: 'ميم بلغتين',
        idea: 'حرف m لاتيني ينزل عموده الأخير وينعطف كذيل الميم العربية. حرف واحد يُقرأ باللغتين، كما تعمل المنصة باللغتين.',
        ink: '#001219',
        accent: '#EE9B00',
        latinFont: "'Kanit', sans-serif",
        latinWeight: 700,
        arabicFont: "'Tajawal', sans-serif",
        arabicWeight: 800,
        draw: (ink, accent) => `
            <g fill="none" stroke-width="5.5" stroke-linecap="round">
                <path stroke="${ink}" d="M8 34 V22 a8 8 0 0 1 16 0 V34"/>
                <path stroke="${accent}" d="M24 22 a8 8 0 0 1 16 0 V36 C40 41.5 36.5 44 31 44"/>
            </g>`,
        guides: () => `
            <circle cx="16" cy="22" r="8"/><circle cx="32" cy="22" r="8"/>
            <path d="M0 14 H48 M0 22 H48 M0 34 H48 M0 44 H48 M8 0 V48 M24 0 V48 M40 0 V48"/>`,
    },
    {
        id: 'iso-box',
        batch: 6,
        title: 'الصندوق المجسّم',
        idea: 'صندوق مفتوح مرسوم بالمنظور، ورسالة تنزل فيه. صندوق الوارد كمكان حقيقي له عمق، تُجمع فيه الرسائل وتُرتّب.',
        ink: '#22223B',
        accent: '#4CC9F0',
        latinFont: "'Comfortaa', sans-serif",
        latinWeight: 700,
        arabicFont: "'Almarai', sans-serif",
        arabicWeight: 800,
        draw: (ink, accent) => `
            <path d="M10 21 L24 14 L38 21" fill="none" stroke="${ink}" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
            <path d="M18 6 L30 12 V25 L18 19 Z" fill="${accent}"/>
            <path d="M10 21 L24 28 V42 L10 35 Z" fill="${ink}"/>
            <path d="M24 28 L38 21 V35 L24 42 Z" fill="${ink}" opacity=".78"/>`,
        guides: () => `
            <path d="M10 21 L24 14 L38 21 L24 28 Z M0 21 H48 M0 35 H48 M10 0 V48 M24 0 V48 M38 0 V48"/>`,
    },
];

/**
 * A wax seal's edge: a circle whose radius rises and falls `bumps` times, as
 * one closed path of short straight steps.
 */
function scallopPath(cx, cy, radius, depth, bumps) {
    const steps = bumps * 12;
    const points = Array.from({ length: steps }, (_, i) => {
        const angle = (i / steps) * Math.PI * 2;
        const r = radius + depth * Math.cos(bumps * angle);

        return `${(cx + r * Math.sin(angle)).toFixed(2)} ${(cy - r * Math.cos(angle)).toFixed(2)}`;
    });

    return `M${points.join(' L')} Z`;
}

let symbolUid = 0;

/** The symbol alone, optionally over its construction grid. */
function symbolSvg(concept, { ink = concept.ink, accent = concept.accent, size = 48, grid = false, label = '' } = {}) {
    const uid = `${concept.id}-${++symbolUid}`;
    const gridLines = grid
        ? `<g class="grid" aria-hidden="true">
               <path stroke="var(--grid-fine)" stroke-width=".12" d="${Array.from({ length: 11 }, (_, i) => `M${(i + 1) * 4} 0 V48 M0 ${(i + 1) * 4} H48`).join(' ')}"/>
               <g fill="none" stroke="var(--grid-guide)" stroke-width=".28">${concept.guides()}</g>
           </g>`
        : '';

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="${size}" height="${size}" ${label ? `role="img" aria-label="${label}"` : 'aria-hidden="true"'}>${gridLines}${concept.draw(ink, accent, uid)}</svg>`;
}

/**
 * The symbol in one colour. A filled symbol (`knockout`) cuts its details out
 * in the background colour instead, or they would vanish into the fill.
 */
function monoColors(concept, color, background) {
    return { ink: color, accent: concept.knockout ? background : color };
}

/** The symbol in white on a rounded square of the concept's ink; a filled one keeps its accent and cuts its details out in white. */
function appIconSvg(concept, { size = 48, mono = false } = {}) {
    const uid = `${concept.id}-${++symbolUid}`;
    const colors = concept.knockout
        ? { ink: concept.accent, accent: concept.ink }
        : { ink: '#FFFFFF', accent: mono ? '#FFFFFF' : concept.accent };

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="${size}" height="${size}" aria-hidden="true">
        <rect width="48" height="48" rx="11" fill="${concept.ink}"/>
        <g transform="translate(7.2 7.2) scale(.7)">${concept.draw(colors.ink, colors.accent, uid)}</g>
    </svg>`;
}
