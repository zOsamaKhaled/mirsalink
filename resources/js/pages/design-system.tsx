import { Head } from '@inertiajs/react';
import {
    FileText,
    Moon,
    NotebookPen,
    Paperclip,
    Search,
    Send,
    Sun,
    Tag,
    UserPlus,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';

import {
    MirsalinkAppIcon,
    MirsalinkSymbol,
    MirsalinkWordmark,
} from '@/components/brand/mirsalink-logo';
import {
    BackArrow,
    ForwardArrow,
    ForwardChevron,
} from '@/components/direction-icons';
import { AgentStatus } from '@/components/inbox/agent-status';
import { CategoryTag, TAG_TONES } from '@/components/inbox/category-tag';
import { ConversationItem } from '@/components/inbox/conversation-item';
import { HandoverMark } from '@/components/inbox/handover-mark';
import { MessageBubble } from '@/components/inbox/message-bubble';
import { SystemEvent } from '@/components/inbox/system-event';
import { WindowTimer } from '@/components/inbox/window-timer';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAppearance } from '@/hooks/use-appearance';
import { DirectionContext } from '@/hooks/use-direction';
import { cn } from '@/lib/utils';

type Lang = 'ar' | 'en';

/*
 * The living reference for Mirsalink's design system: every token and
 * component as it really renders, in both languages and both themes.
 * Its rules are written out in docs/design-system.md.
 */

const COPY = {
    ar: {
        title: 'نظام التصميم',
        intro: 'المرجع الحي لواجهة ميرسالينك: الألوان والخطوط والمكونات كما تظهر فعلًا، بالعربية والإنجليزية، في الوضعين الفاتح والداكن.',
        theme: { light: 'فاتح', dark: 'داكن' },
        sections: {
            brand: 'الشعار',
            color: 'الألوان',
            type: 'الخطوط',
            space: 'المسافات والزوايا',
            components: 'المكونات',
            conversation: 'المحادثة',
            inbox: 'قائمة المحادثات',
            direction: 'الاتجاه',
        },
        brand: {
            lead: 'عصوان تتسلّم إحداهما من الأخرى: الكحلية المنصة، والوردية الشخص الذي تُسلَّم إليه المحادثة.',
            onLight: 'على خلفية فاتحة',
            onNavy: 'على الكحلي',
            mono: 'بلون واحد',
            appIcon: 'أيقونة التطبيق',
            rules: [
                'المساحة الخالية حول الرمز لا تقل عن نصف عرضه.',
                'أصغر حجم للرمز 16 بكسل، وللشعار مع الاسم 96 بكسل عرضًا.',
                'لا تُغيَّر زاوية العصوين ولا ألوانهما، ولا يوضع الرمز على صورة مزدحمة.',
            ],
        },
        color: {
            lead: 'الكحلي هو المنصة: الأزرار والتنقل وما يرسله الفريق. الوردي هو التسليم: محادثة تحتاج إنسانًا، وما هو جديد لك. نُبقي الوردي قليلًا حتى يبقى معناه واضحًا.',
            groups: {
                brand: 'الهوية',
                surface: 'الأسطح والنص',
                action: 'الأفعال',
                handover: 'التسليم',
                status: 'الحالات',
                tags: 'ألوان الوسوم',
            },
            tagsNote:
                'سبعة ألوان يختار منها الفريق لكل وسم. الوردي ليس منها، فهو للتسليم وحده.',
            roles: {
                'brand-navy': 'لون المنصة',
                'brand-rose': 'لون التسليم',
                background: 'خلفية الصفحة',
                card: 'البطاقات واللوحات',
                sunken: 'خلفية المحادثة',
                foreground: 'النص الأساسي',
                'muted-foreground': 'النص الثانوي',
                border: 'الحدود',
                primary: 'الفعل الأساسي',
                secondary: 'فعل ثانوي',
                accent: 'التمرير والتحديد',
                ring: 'إطار التركيز',
                handover: 'تعبئة التسليم',
                'handover-ink': 'نص بلون التسليم',
                'handover-soft': 'خلفية التسليم',
                success: 'نجاح، متاح',
                warning: 'تنبيه، يقترب',
                info: 'معلومة',
                destructive: 'خطأ، حذف',
            } as Record<string, string>,
        },
        type: {
            lead: 'Almarai للواجهة والعناوين باللغتين: صُمّم لعلامة سعودية فهو مألوف للعين هنا، وواضح وجاد. أوزانه أربعة: 300 و400 و700 و800. وIBM Plex Mono لأرقام الهواتف والمعرّفات. الأرقام لاتينية في اللغتين.',
            families: {
                sans: 'الواجهة والنصوص',
                display: 'العناوين والأرقام الكبيرة',
                mono: 'الهواتف والمعرّفات',
            },
            sample: 'وصل عميل محتمل، وسلّمه المساعد الذكي إلى سارة.',
            scale: 'سلّم المقاسات',
        },
        space: {
            lead: 'المسافات من مضاعفات 4 بكسل. الزوايا مستديرة كطرفي العصوين في الشعار، والحبوب (الوسوم والعدّادات) دائرية تمامًا.',
            spacing: 'المسافات',
            radius: 'الزوايا',
            shadow: 'الظلال',
            shadows: {
                raised: 'مرتفع: البطاقات والفقاعات',
                overlay: 'طافٍ: القوائم والنوافذ',
            },
        },
        components: {
            buttons: 'الأزرار',
            buttonLabels: {
                primary: 'إرسال',
                handover: 'استلام المحادثة',
                secondary: 'إرسال قالب',
                outline: 'إضافة وسم',
                ghost: 'إلغاء',
                destructive: 'حذف القالب',
                link: 'عرض السجل',
                disabled: 'غير متاح',
            },
            handoverRule:
                'زر التسليم الوردي يظهر مرة واحدة على الأكثر في الشاشة: حين ينتظر عميل إنسانًا.',
            fields: 'الحقول',
            name: 'اسم القالب',
            namePlaceholder: 'مثال: تذكير بانتهاء الاشتراك',
            nameHint: 'حروف إنجليزية صغيرة وشرطة سفلية، كما تطلب Meta.',
            phone: 'رقم واتساب',
            phoneError: 'الرقم ناقص. اكتبه بالمفتاح الدولي، مثل +966.',
            search: 'ابحث بالاسم أو الرقم',
            badges: 'الشارات',
            badgeLabels: {
                success: 'وصلت',
                warning: 'تقترب من الانتهاء',
                info: 'جديد',
                danger: 'فشلت',
                neutral: 'مغلقة',
                handover: 'بانتظارك',
            },
            people: 'الفريق وتوفّره',
            availability: { available: 'متاح', busy: 'مشغول', away: 'غائب' },
            window: 'نافذة الـ24 ساعة',
            windowLabels: {
                open: 'باقٍ 21 ساعة للرد',
                closing: 'باقٍ ساعة و40 دقيقة',
                closed: 'انتهت النافذة: أرسل قالبًا',
            },
            handoverMark: 'علامة التسليم',
            handoverMarkNote:
                'العصوان الصغيرتان لا تظهران إلا حين يسلّم المساعد محادثة لإنسان.',
        },
        conversation: {
            lead: 'العميل يكتب في جهة البداية، والفريق في جهة النهاية. ردود المساعد كحلي فاتح، وردود الموظف كحلي داكن، والملاحظة الداخلية على أصفر الورق ولا يراها العميل.',
            customer: 'عبدالله الحربي',
            company: 'شركة نماء للمقاولات',
            assign: 'إسناد',
            bot: 'المساعد الذكي',
            agent: 'سارة',
            note: 'ملاحظة داخلية · سارة',
            messages: {
                hello: 'السلام عليكم، أبغى أعرف أسعار باقات الشركات.',
                botReply:
                    'وعليكم السلام! باقات الشركات ثلاث: الأساسية والنمو والمتقدمة. كم موظفًا عندكم تقريبًا حتى أرشّح لك الأنسب؟',
                tagged: 'صنّف المساعد المحادثة: عميل محتمل، صاحب شركة',
                askHuman: 'عندنا 40 موظف. ممكن أكلم أحد من المبيعات؟',
                handover: 'سلّم المساعد المحادثة إلى سارة',
                note: 'شركة مقاولات في الرياض. أرسل له عرض الباقة المتقدمة مع خصم السنة.',
                agentReply:
                    'أهلًا عبدالله، معك سارة من فريق المبيعات. جهّزت لك عرضًا يناسب 40 موظفًا.',
                attachment: 'عرض الأسعار.pdf',
                failure: 'لم تصل. أعد المحاولة',
            },
            status: { read: 'قُرئت', delivered: 'وصلت', failed: 'فشلت' },
            composer: 'اكتب ردًا…',
            template: 'قالب',
            noteToggle: 'ملاحظة',
            send: 'إرسال',
        },
        inbox: {
            lead: 'العدد الوردي رسائل لم تُقرأ، والعلامة قبل الاسم محادثة سلّمها المساعد ولم يستلمها أحد بعد.',
            tabs: ['محادثاتي', 'غير مُسندة', 'الكل'],
            unread: (count: number) => `${count} رسائل غير مقروءة`,
            items: [
                {
                    name: 'عبدالله الحربي',
                    initials: 'ع',
                    preview: 'عندنا 40 موظف. ممكن أكلم أحد من المبيعات؟',
                    time: '10:05',
                    unread: 2,
                    waiting: true,
                },
                {
                    name: 'مريم القحطاني',
                    initials: 'م',
                    preview: 'المساعد: تقدرين ترفعين السيرة الذاتية من حسابك.',
                    time: '09:48',
                },
                {
                    name: 'فهد العتيبي',
                    initials: 'ف',
                    preview: 'الدفع ما يمشي مع بطاقة مدى',
                    time: '09:31',
                    unread: 1,
                },
                {
                    name: 'شركة البناء الحديث',
                    initials: 'ش',
                    preview: 'أنت: تم تفعيل الاشتراك، شكرًا لك.',
                    time: 'أمس',
                },
            ],
            tags: {
                lead: 'عميل محتمل',
                owner: 'صاحب شركة',
                seeker: 'باحث عن عمل',
                billing: 'دفع وفوترة',
                support: 'دعم فني',
            },
            botHandles: 'المساعد',
            agents: { sara: 'سارة', khalid: 'خالد' },
        },
        direction: {
            lead: 'السهم الذي يعني «إلى الأمام» يتجه مع اتجاه القراءة: إلى اليسار في العربية وإلى اليمين في الإنجليزية. نختاره بمعناه، ولا نقلب السهم بـ CSS.',
            next: 'التالي',
            back: 'السابق',
            viewAll: 'عرض كل المحادثات',
        },
    },
    en: {
        title: 'Design system',
        intro: "The living reference for Mirsalink's interface: colours, type and components as they really render, in Arabic and English, light and dark.",
        theme: { light: 'Light', dark: 'Dark' },
        sections: {
            brand: 'Logo',
            color: 'Colour',
            type: 'Type',
            space: 'Space and corners',
            components: 'Components',
            conversation: 'Conversation',
            inbox: 'Inbox list',
            direction: 'Direction',
        },
        brand: {
            lead: 'Two batons in a hand-off: the navy one is the platform, the rose one the person the conversation is handed to.',
            onLight: 'On light',
            onNavy: 'On navy',
            mono: 'One colour',
            appIcon: 'App icon',
            rules: [
                'Keep clear space of at least half the symbol’s width around it.',
                'The symbol is never smaller than 16 px; the full logo never narrower than 96 px.',
                'Never change the bars’ angle or colours, and never set the symbol on a busy photo.',
            ],
        },
        color: {
            lead: 'Navy is the platform: buttons, navigation, what the team sends. Rose is the handover: a conversation that needs a person, what is new for you. Rose stays scarce so it keeps that meaning.',
            groups: {
                brand: 'Identity',
                surface: 'Surfaces and text',
                action: 'Actions',
                handover: 'Handover',
                status: 'Status',
                tags: 'Tag colours',
            },
            tagsNote:
                'Seven colours a team picks from for each tag. Rose is not one of them: it belongs to the handover.',
            roles: {
                'brand-navy': 'The platform',
                'brand-rose': 'The handover',
                background: 'Page background',
                card: 'Cards and panels',
                sunken: 'Conversation background',
                foreground: 'Main text',
                'muted-foreground': 'Secondary text',
                border: 'Borders',
                primary: 'Main action',
                secondary: 'Secondary action',
                accent: 'Hover and selection',
                ring: 'Focus ring',
                handover: 'Handover fill',
                'handover-ink': 'Handover text',
                'handover-soft': 'Handover background',
                success: 'Done, available',
                warning: 'Warning, running out',
                info: 'Information',
                destructive: 'Error, delete',
            } as Record<string, string>,
        },
        type: {
            lead: 'Almarai for the interface and headings in both languages: designed for a Saudi brand, so it looks familiar here, clear and serious. It has four weights: 300, 400, 700 and 800. IBM Plex Mono for phone numbers and IDs. Digits are Latin in both languages.',
            families: {
                sans: 'Interface and text',
                display: 'Headings and big figures',
                mono: 'Phone numbers and IDs',
            },
            sample: 'A new lead wrote in, and the assistant handed them to Sara.',
            scale: 'Type scale',
        },
        space: {
            lead: 'Spacing comes in steps of 4 px. Corners are round like the ends of the logo’s bars, and pills (tags, counts) are fully round.',
            spacing: 'Spacing',
            radius: 'Corners',
            shadow: 'Shadows',
            shadows: {
                raised: 'Raised: cards and bubbles',
                overlay: 'Overlay: menus and dialogs',
            },
        },
        components: {
            buttons: 'Buttons',
            buttonLabels: {
                primary: 'Send',
                handover: 'Take conversation',
                secondary: 'Send template',
                outline: 'Add tag',
                ghost: 'Cancel',
                destructive: 'Delete template',
                link: 'View history',
                disabled: 'Unavailable',
            },
            handoverRule:
                'The rose handover button appears at most once on a screen: when a customer is waiting for a person.',
            fields: 'Fields',
            name: 'Template name',
            namePlaceholder: 'e.g. subscription_expiry_reminder',
            nameHint: 'Lowercase letters and underscores, as Meta requires.',
            phone: 'WhatsApp number',
            phoneError:
                'The number is incomplete. Start with the country code, like +966.',
            search: 'Search by name or number',
            badges: 'Badges',
            badgeLabels: {
                success: 'Delivered',
                warning: 'Running out',
                info: 'New',
                danger: 'Failed',
                neutral: 'Closed',
                handover: 'Waiting for you',
            },
            people: 'Team and availability',
            availability: {
                available: 'Available',
                busy: 'Busy',
                away: 'Away',
            },
            window: 'The 24-hour window',
            windowLabels: {
                open: '21 h left to reply',
                closing: '1 h 40 min left',
                closed: 'Window closed: send a template',
            },
            handoverMark: 'Handover mark',
            handoverMarkNote:
                'The two small bars appear only where the assistant hands a conversation to a person.',
        },
        conversation: {
            lead: "The customer writes on the start side, the team on the end side. The assistant's replies are pale navy, a person's solid navy, and an internal note sits on paper yellow where the customer never sees it.",
            customer: 'Abdullah Alharbi',
            company: 'Namaa Contracting',
            assign: 'Assign',
            bot: 'Assistant',
            agent: 'Sara',
            note: 'Internal note · Sara',
            messages: {
                hello: 'Hello, I’d like to know the prices of your company plans.',
                botReply:
                    'Hello! There are three company plans: Basic, Growth and Advanced. Roughly how many employees do you have, so I can suggest the right one?',
                tagged: 'The assistant tagged this: Lead, Company owner',
                askHuman:
                    'We have 40 employees. Can I talk to someone in sales?',
                handover: 'The assistant handed this conversation to Sara',
                note: 'Contracting firm in Riyadh. Send the Advanced plan offer with the yearly discount.',
                agentReply:
                    'Hi Abdullah, this is Sara from sales. I’ve prepared an offer for 40 employees.',
                attachment: 'Price offer.pdf',
                failure: 'Not delivered. Try again',
            },
            status: { read: 'Read', delivered: 'Delivered', failed: 'Failed' },
            composer: 'Write a reply…',
            template: 'Template',
            noteToggle: 'Note',
            send: 'Send',
        },
        inbox: {
            lead: 'The rose count is unread messages; the mark before a name is a conversation the assistant handed over that no one has taken yet.',
            tabs: ['Mine', 'Unassigned', 'All'],
            unread: (count: number) => `${count} unread messages`,
            items: [
                {
                    name: 'Abdullah Alharbi',
                    initials: 'AA',
                    preview:
                        'We have 40 employees. Can I talk to someone in sales?',
                    time: '10:05',
                    unread: 2,
                    waiting: true,
                },
                {
                    name: 'Maryam Alqahtani',
                    initials: 'MA',
                    preview:
                        'Assistant: You can upload your CV from your account.',
                    time: '09:48',
                },
                {
                    name: 'Fahad Alotaibi',
                    initials: 'FA',
                    preview: 'Payment fails with my mada card',
                    time: '09:31',
                    unread: 1,
                },
                {
                    name: 'Modern Building Co.',
                    initials: 'MB',
                    preview: 'You: Your subscription is active, thank you.',
                    time: 'Yesterday',
                },
            ],
            tags: {
                lead: 'Lead',
                owner: 'Company owner',
                seeker: 'Job seeker',
                billing: 'Billing',
                support: 'Support',
            },
            botHandles: 'Assistant',
            agents: { sara: 'Sara', khalid: 'Khalid' },
        },
        direction: {
            lead: 'An arrow that means "forward" points the way the page reads: left in Arabic, right in English. Pick it by its meaning, and never flip it with CSS.',
            next: 'Next',
            back: 'Back',
            viewAll: 'View all conversations',
        },
    },
} as const;

const SECTION_IDS = [
    'brand',
    'color',
    'type',
    'space',
    'components',
    'conversation',
    'inbox',
    'direction',
] as const;

const COLOR_GROUPS: {
    key: keyof (typeof COPY)['ar']['color']['groups'];
    tokens: string[];
}[] = [
    { key: 'brand', tokens: ['brand-navy', 'brand-rose'] },
    {
        key: 'surface',
        tokens: [
            'background',
            'card',
            'sunken',
            'foreground',
            'muted-foreground',
            'border',
        ],
    },
    { key: 'action', tokens: ['primary', 'secondary', 'accent', 'ring'] },
    { key: 'handover', tokens: ['handover', 'handover-ink', 'handover-soft'] },
    { key: 'status', tokens: ['success', 'warning', 'info', 'destructive'] },
];

export default function DesignSystem() {
    const [lang, setLang] = useState<Lang>('ar');
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const t = COPY[lang];
    const dir = lang === 'ar' ? 'rtl' : 'ltr';

    return (
        <DirectionContext.Provider value={dir}>
            <Head title={t.title} />
            <div dir={dir} lang={lang} className="min-h-screen bg-background">
                <header className="sticky top-0 z-20 border-b bg-card/90 backdrop-blur">
                    <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-3 px-4 py-3 sm:px-6">
                        <MirsalinkWordmark lang={lang} className="text-xl" />
                        <span className="hidden h-6 w-px bg-border sm:block" />
                        <span className="font-display text-title font-semibold">
                            {t.title}
                        </span>
                        <div className="ms-auto flex items-center gap-2">
                            <Segmented
                                label="Language"
                                value={lang}
                                onChange={(value) => setLang(value as Lang)}
                                options={[
                                    { value: 'ar', label: 'العربية' },
                                    { value: 'en', label: 'English' },
                                ]}
                            />
                            <Segmented
                                label="Theme"
                                value={resolvedAppearance}
                                onChange={(value) =>
                                    updateAppearance(value as 'light' | 'dark')
                                }
                                options={[
                                    {
                                        value: 'light',
                                        label: t.theme.light,
                                        icon: <Sun className="size-4" />,
                                    },
                                    {
                                        value: 'dark',
                                        label: t.theme.dark,
                                        icon: <Moon className="size-4" />,
                                    },
                                ]}
                            />
                        </div>
                    </div>
                </header>

                <div className="mx-auto grid max-w-6xl gap-10 px-4 py-10 sm:px-6 lg:grid-cols-[11rem_minmax(0,1fr)]">
                    <nav className="hidden lg:block" aria-label={t.title}>
                        <ul className="sticky top-24 flex flex-col gap-1 text-body-sm">
                            {SECTION_IDS.map((id) => (
                                <li key={id}>
                                    <a
                                        href={`#${id}`}
                                        className="block rounded-lg px-3 py-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                                    >
                                        {t.sections[id]}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </nav>

                    <main className="flex min-w-0 flex-col gap-16">
                        <div className="flex flex-col gap-3">
                            <h1 className="font-display text-display font-bold">
                                {t.title}
                            </h1>
                            <p className="max-w-[62ch] text-body text-muted-foreground">
                                {t.intro}
                            </p>
                        </div>

                        <BrandSection lang={lang} />
                        <ColorSection lang={lang} theme={resolvedAppearance} />
                        <TypeSection lang={lang} />
                        <SpaceSection lang={lang} />
                        <ComponentsSection lang={lang} />
                        <ConversationSection lang={lang} />
                        <InboxSection lang={lang} />
                        <DirectionSection lang={lang} />
                    </main>
                </div>
            </div>
        </DirectionContext.Provider>
    );
}

function Section({
    id,
    title,
    lead,
    children,
}: {
    id: string;
    title: string;
    lead?: string;
    children: ReactNode;
}) {
    return (
        <section id={id} className="flex scroll-mt-24 flex-col gap-6">
            <div className="flex flex-col gap-2">
                <h2 className="font-display text-heading font-semibold">
                    {title}
                </h2>
                {lead && (
                    <p className="max-w-[68ch] text-body text-muted-foreground">
                        {lead}
                    </p>
                )}
            </div>
            {children}
        </section>
    );
}

function Panel({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('rounded-xl border bg-card p-5', className)}>
            {children}
        </div>
    );
}

function Caption({ children }: { children: ReactNode }) {
    return <p className="text-caption text-muted-foreground">{children}</p>;
}

function Segmented({
    label,
    value,
    onChange,
    options,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: { value: string; label: string; icon?: ReactNode }[];
}) {
    return (
        <div
            role="radiogroup"
            aria-label={label}
            className="inline-flex rounded-full bg-muted p-1"
        >
            {options.map((option) => (
                <button
                    key={option.value}
                    type="button"
                    role="radio"
                    aria-checked={value === option.value}
                    onClick={() => onChange(option.value)}
                    className={cn(
                        'inline-flex h-8 items-center gap-1.5 rounded-full px-3 text-body-sm transition-colors',
                        'focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                        value === option.value
                            ? 'bg-card font-semibold text-foreground shadow-raised'
                            : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    {option.icon}
                    {option.label}
                </button>
            ))}
        </div>
    );
}

function BrandSection({ lang }: { lang: Lang }) {
    const t = COPY[lang];

    return (
        <Section id="brand" title={t.sections.brand} lead={t.brand.lead}>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="flex flex-col items-center gap-4 rounded-xl border bg-white p-5">
                    <MirsalinkSymbol tone="color" className="size-20" />
                    <p className="text-caption text-[#586178]">
                        {t.brand.onLight}
                    </p>
                </div>
                <div className="flex flex-col items-center gap-4 rounded-xl bg-brand-navy p-5">
                    <MirsalinkSymbol tone="on-dark" className="size-20" />
                    <p className="text-caption text-white/75">
                        {t.brand.onNavy}
                    </p>
                </div>
                <Panel className="flex flex-col items-center gap-4">
                    <MirsalinkSymbol
                        tone="mono"
                        className="size-20 text-foreground"
                    />
                    <Caption>{t.brand.mono}</Caption>
                </Panel>
                <Panel className="flex flex-col items-center gap-4">
                    <MirsalinkAppIcon className="size-20" />
                    <Caption>{t.brand.appIcon}</Caption>
                </Panel>
            </div>
            <div className="grid gap-4 md:grid-cols-2">
                <Panel className="flex items-center justify-center py-10">
                    <MirsalinkWordmark lang="ar" className="text-4xl" />
                </Panel>
                <Panel className="flex items-center justify-center py-10">
                    <MirsalinkWordmark lang="en" className="text-4xl" />
                </Panel>
            </div>
            <ul className="flex flex-col gap-2 text-body-sm text-muted-foreground">
                {t.brand.rules.map((rule) => (
                    <li key={rule} className="flex gap-2">
                        <span className="mt-2 size-1.5 shrink-0 rounded-full bg-primary" />
                        {rule}
                    </li>
                ))}
            </ul>
        </Section>
    );
}

function ColorSection({ lang, theme }: { lang: Lang; theme: string }) {
    const t = COPY[lang];

    return (
        <Section id="color" title={t.sections.color} lead={t.color.lead}>
            {COLOR_GROUPS.map((group) => (
                <div key={group.key} className="flex flex-col gap-3">
                    <h3 className="text-title font-semibold">
                        {t.color.groups[group.key]}
                    </h3>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        {group.tokens.map((token) => (
                            <Swatch
                                key={token}
                                token={token}
                                role={t.color.roles[token]}
                                theme={theme}
                            />
                        ))}
                    </div>
                </div>
            ))}
            <div className="flex flex-col gap-3">
                <h3 className="text-title font-semibold">
                    {t.color.groups.tags}
                </h3>
                <p className="text-body-sm text-muted-foreground">
                    {t.color.tagsNote}
                </p>
                <div className="flex flex-wrap gap-2">
                    {TAG_TONES.map((tone) => (
                        <CategoryTag key={tone} tone={tone}>
                            <span dir="ltr">tag-{tone}</span>
                        </CategoryTag>
                    ))}
                </div>
            </div>
        </Section>
    );
}

function Swatch({
    token,
    role,
    theme,
}: {
    token: string;
    role: string;
    theme: string;
}) {
    const [hex, setHex] = useState('');

    useEffect(() => {
        setHex(
            getComputedStyle(document.documentElement)
                .getPropertyValue(`--${token}`)
                .trim()
                .toUpperCase(),
        );
    }, [token, theme]);

    return (
        <div className="overflow-hidden rounded-xl border bg-card">
            <div
                className="h-16 border-b"
                style={{ background: `var(--${token})` }}
            />
            <div className="flex flex-col gap-0.5 p-3">
                <span className="text-body-sm font-semibold">{role}</span>
                <span
                    dir="ltr"
                    className="font-mono text-caption text-muted-foreground"
                >
                    {token} · {hex}
                </span>
            </div>
        </div>
    );
}

const TYPE_SCALE = [
    {
        name: 'hero',
        className: 'text-hero font-display font-bold',
        size: '40 / 46',
    },
    {
        name: 'display',
        className: 'text-display font-display font-bold',
        size: '30 / 38',
    },
    {
        name: 'heading',
        className: 'text-heading font-display font-semibold',
        size: '22 / 30',
    },
    { name: 'title', className: 'text-title font-semibold', size: '18 / 26' },
    { name: 'body', className: 'text-body', size: '15 / 25' },
    { name: 'body-sm', className: 'text-body-sm', size: '13 / 20' },
    { name: 'caption', className: 'text-caption', size: '12 / 18' },
];

function TypeSection({ lang }: { lang: Lang }) {
    const t = COPY[lang];

    return (
        <Section id="type" title={t.sections.type} lead={t.type.lead}>
            <div className="grid gap-4 md:grid-cols-3">
                {(
                    [
                        ['sans', 'Almarai 400 / 700', 'font-sans'],
                        [
                            'display',
                            'Almarai 800',
                            'font-display font-extrabold',
                        ],
                        ['mono', 'IBM Plex Mono', 'font-mono'],
                    ] as const
                ).map(([key, family, className]) => (
                    <Panel key={key} className="flex flex-col gap-3">
                        <span
                            dir={key === 'mono' ? 'ltr' : undefined}
                            className={cn(
                                'text-start text-display leading-none',
                                className,
                            )}
                        >
                            {key === 'mono'
                                ? '+966 55'
                                : lang === 'ar'
                                  ? 'أبجد Aa'
                                  : 'Aa أبجد'}
                        </span>
                        <div className="flex flex-col gap-0.5">
                            <span
                                dir="ltr"
                                className="text-start text-body-sm font-semibold"
                            >
                                {family}
                            </span>
                            <Caption>{t.type.families[key]}</Caption>
                        </div>
                    </Panel>
                ))}
            </div>
            <Panel className="flex flex-col divide-y p-0">
                <h3 className="px-5 py-3 text-body-sm font-semibold text-muted-foreground">
                    {t.type.scale}
                </h3>
                {TYPE_SCALE.map((step) => (
                    <div
                        key={step.name}
                        className="flex flex-col gap-1 px-5 py-4 sm:flex-row sm:items-baseline sm:gap-6"
                    >
                        <span
                            dir="ltr"
                            className="w-32 shrink-0 text-start font-mono text-caption text-muted-foreground"
                        >
                            {step.name} · {step.size}
                        </span>
                        <span className={cn('min-w-0', step.className)}>
                            {t.type.sample}
                        </span>
                    </div>
                ))}
            </Panel>
        </Section>
    );
}

const SPACING = [1, 2, 3, 4, 6, 8, 12];
const RADII = [
    ['sm', 'rounded-sm'],
    ['md', 'rounded-md'],
    ['lg', 'rounded-lg'],
    ['xl', 'rounded-xl'],
    ['2xl', 'rounded-2xl'],
    ['full', 'rounded-full'],
] as const;

function SpaceSection({ lang }: { lang: Lang }) {
    const t = COPY[lang];

    return (
        <Section id="space" title={t.sections.space} lead={t.space.lead}>
            <div className="grid gap-4 lg:grid-cols-3">
                <Panel className="flex flex-col gap-3">
                    <h3 className="text-body-sm font-semibold">
                        {t.space.spacing}
                    </h3>
                    {SPACING.map((step) => (
                        <div key={step} className="flex items-center gap-3">
                            <span
                                dir="ltr"
                                className="w-14 font-mono text-caption text-muted-foreground"
                            >
                                {step} · {step * 4}px
                            </span>
                            <span
                                className="h-3 rounded-full bg-primary/80"
                                style={{ width: step * 4 }}
                            />
                        </div>
                    ))}
                </Panel>
                <Panel className="flex flex-col gap-3">
                    <h3 className="text-body-sm font-semibold">
                        {t.space.radius}
                    </h3>
                    <div className="grid grid-cols-3 gap-3">
                        {RADII.map(([name, className]) => (
                            <div
                                key={name}
                                className="flex flex-col items-center gap-1.5"
                            >
                                <span
                                    className={cn(
                                        'size-12 border-2 border-primary bg-accent',
                                        className,
                                    )}
                                />
                                <span
                                    dir="ltr"
                                    className="font-mono text-caption text-muted-foreground"
                                >
                                    {name}
                                </span>
                            </div>
                        ))}
                    </div>
                </Panel>
                <Panel className="flex flex-col gap-4">
                    <h3 className="text-body-sm font-semibold">
                        {t.space.shadow}
                    </h3>
                    <div className="rounded-xl bg-card p-4 shadow-raised ring-1 ring-border">
                        <Caption>{t.space.shadows.raised}</Caption>
                    </div>
                    <div className="rounded-xl bg-popover p-4 shadow-overlay ring-1 ring-border">
                        <Caption>{t.space.shadows.overlay}</Caption>
                    </div>
                </Panel>
            </div>
        </Section>
    );
}

function ComponentsSection({ lang }: { lang: Lang }) {
    const t = COPY[lang].components;
    const sections = COPY[lang].sections;

    return (
        <Section id="components" title={sections.components}>
            <Panel className="flex flex-col gap-4">
                <h3 className="text-title font-semibold">{t.buttons}</h3>
                <div className="flex flex-wrap items-center gap-3">
                    <Button>
                        <Send className="rtl:-scale-x-100" />
                        {t.buttonLabels.primary}
                    </Button>
                    <Button variant="handover">
                        <HandoverMark onRose />
                        {t.buttonLabels.handover}
                    </Button>
                    <Button variant="secondary">
                        <FileText />
                        {t.buttonLabels.secondary}
                    </Button>
                    <Button variant="outline">
                        <Tag />
                        {t.buttonLabels.outline}
                    </Button>
                    <Button variant="ghost">{t.buttonLabels.ghost}</Button>
                    <Button variant="destructive">
                        {t.buttonLabels.destructive}
                    </Button>
                    <Button variant="link">{t.buttonLabels.link}</Button>
                    <Button disabled>{t.buttonLabels.disabled}</Button>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <Button size="sm">{t.buttonLabels.primary}</Button>
                    <Button>{t.buttonLabels.primary}</Button>
                    <Button size="lg">{t.buttonLabels.primary}</Button>
                    <Button
                        size="icon"
                        variant="outline"
                        aria-label={t.buttonLabels.outline}
                    >
                        <UserPlus />
                    </Button>
                    <Button
                        size="icon-sm"
                        variant="ghost"
                        aria-label={t.search}
                    >
                        <Search />
                    </Button>
                </div>
                <Caption>{t.handoverRule}</Caption>
            </Panel>

            <div className="grid gap-4 lg:grid-cols-2">
                <Panel className="flex flex-col gap-5">
                    <h3 className="text-title font-semibold">{t.fields}</h3>
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="ds-template-name">{t.name}</Label>
                        <Input
                            id="ds-template-name"
                            placeholder={t.namePlaceholder}
                        />
                        <Caption>{t.nameHint}</Caption>
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="ds-phone">{t.phone}</Label>
                        <Input
                            id="ds-phone"
                            dir="ltr"
                            defaultValue="+966 55 123"
                            aria-invalid
                            aria-describedby="ds-phone-error"
                            className="text-start font-mono"
                        />
                        <p
                            id="ds-phone-error"
                            className="text-caption text-danger"
                        >
                            {t.phoneError}
                        </p>
                    </div>
                    <div className="relative">
                        <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            aria-label={t.search}
                            placeholder={t.search}
                            className="ps-9"
                        />
                    </div>
                </Panel>

                <div className="flex flex-col gap-4">
                    <Panel className="flex flex-col gap-3">
                        <h3 className="text-title font-semibold">{t.badges}</h3>
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="success">
                                {t.badgeLabels.success}
                            </Badge>
                            <Badge variant="warning">
                                {t.badgeLabels.warning}
                            </Badge>
                            <Badge variant="info">{t.badgeLabels.info}</Badge>
                            <Badge variant="danger">
                                {t.badgeLabels.danger}
                            </Badge>
                            <Badge variant="neutral">
                                {t.badgeLabels.neutral}
                            </Badge>
                            <Badge variant="handover">
                                {t.badgeLabels.handover}
                            </Badge>
                        </div>
                    </Panel>
                    <Panel className="flex flex-col gap-3">
                        <h3 className="text-title font-semibold">{t.people}</h3>
                        <div className="flex flex-wrap items-center gap-5">
                            {(['available', 'busy', 'away'] as const).map(
                                (status) => (
                                    <AgentStatus
                                        key={status}
                                        status={status}
                                        label={t.availability[status]}
                                    />
                                ),
                            )}
                        </div>
                    </Panel>
                    <Panel className="flex flex-col gap-3">
                        <h3 className="text-title font-semibold">{t.window}</h3>
                        <div className="flex flex-wrap gap-2">
                            <WindowTimer
                                hoursLeft={21}
                                label={t.windowLabels.open}
                            />
                            <WindowTimer
                                hoursLeft={1.6}
                                label={t.windowLabels.closing}
                            />
                            <WindowTimer
                                hoursLeft={0}
                                label={t.windowLabels.closed}
                            />
                        </div>
                    </Panel>
                    <Panel className="flex items-center gap-4">
                        <HandoverMark
                            animated
                            className="size-10 text-primary"
                        />
                        <div className="flex flex-col gap-0.5">
                            <h3 className="text-body-sm font-semibold">
                                {t.handoverMark}
                            </h3>
                            <Caption>{t.handoverMarkNote}</Caption>
                        </div>
                    </Panel>
                </div>
            </div>
        </Section>
    );
}

function ConversationSection({ lang }: { lang: Lang }) {
    const t = COPY[lang].conversation;
    const inbox = COPY[lang].inbox;
    const sections = COPY[lang].sections;

    return (
        <Section id="conversation" title={sections.conversation} lead={t.lead}>
            <div className="overflow-hidden rounded-2xl border bg-card shadow-raised">
                <div className="flex flex-wrap items-center gap-3 border-b px-4 py-3">
                    <Avatar className="size-10">
                        <AvatarFallback className="bg-secondary font-display font-semibold">
                            {inbox.items[0].initials}
                        </AvatarFallback>
                    </Avatar>
                    <div className="flex min-w-0 flex-col">
                        <span className="truncate text-body font-semibold">
                            {t.customer}
                        </span>
                        <span className="flex items-center gap-2 text-caption text-muted-foreground">
                            <span dir="ltr" className="font-mono">
                                +966 55 123 4567
                            </span>
                            · {t.company}
                        </span>
                    </div>
                    <div className="flex flex-wrap items-center gap-2 sm:ms-auto">
                        <CategoryTag tone="blue" byBot>
                            {inbox.tags.lead}
                        </CategoryTag>
                        <CategoryTag tone="violet" byBot>
                            {inbox.tags.owner}
                        </CategoryTag>
                        <WindowTimer
                            hoursLeft={21}
                            label={COPY[lang].components.windowLabels.open}
                        />
                        <Button size="sm" variant="outline">
                            <UserPlus />
                            {t.assign}
                        </Button>
                    </div>
                </div>

                <div className="flex flex-col gap-3 bg-sunken px-4 py-5">
                    <MessageBubble kind="customer" time="10:02">
                        {t.messages.hello}
                    </MessageBubble>
                    <MessageBubble
                        kind="bot"
                        author={t.bot}
                        time="10:02"
                        status={{ status: 'read', label: t.status.read }}
                    >
                        {t.messages.botReply}
                    </MessageBubble>
                    <SystemEvent
                        icon={<Tag className="size-3.5" />}
                        time="10:03"
                    >
                        {t.messages.tagged}
                    </SystemEvent>
                    <MessageBubble kind="customer" time="10:05">
                        {t.messages.askHuman}
                    </MessageBubble>
                    <SystemEvent handover time="10:05">
                        {t.messages.handover}
                    </SystemEvent>
                    <MessageBubble kind="note" author={t.note} time="10:06">
                        {t.messages.note}
                    </MessageBubble>
                    <MessageBubble
                        kind="agent"
                        author={t.agent}
                        time="10:07"
                        status={{
                            status: 'delivered',
                            label: t.status.delivered,
                        }}
                    >
                        {t.messages.agentReply}
                    </MessageBubble>
                    <MessageBubble
                        kind="agent"
                        time="10:07"
                        status={{ status: 'failed', label: t.status.failed }}
                        failure={t.messages.failure}
                    >
                        <span className="inline-flex items-center gap-2">
                            <FileText className="size-4" />
                            {t.messages.attachment}
                        </span>
                    </MessageBubble>
                </div>

                <div className="flex flex-col gap-2 border-t p-3">
                    <div className="flex items-center gap-2">
                        <Button
                            size="icon"
                            variant="ghost"
                            aria-label={t.template}
                        >
                            <Paperclip />
                        </Button>
                        <Input
                            aria-label={t.composer}
                            placeholder={t.composer}
                            className="h-10 flex-1 rounded-full bg-muted/60 px-4"
                        />
                        <Button className="h-10 rounded-full px-5">
                            <Send className="rtl:-scale-x-100" />
                            {t.send}
                        </Button>
                    </div>
                    <div className="flex items-center gap-2 ps-12">
                        <Button
                            size="sm"
                            variant="secondary"
                            className="rounded-full"
                        >
                            <FileText />
                            {t.template}
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            className="rounded-full"
                        >
                            <NotebookPen />
                            {t.noteToggle}
                        </Button>
                    </div>
                </div>
            </div>
        </Section>
    );
}

function InboxSection({ lang }: { lang: Lang }) {
    const t = COPY[lang].inbox;
    const sections = COPY[lang].sections;
    const [selected, setSelected] = useState(0);
    const tagsFor = [
        [
            { label: t.tags.lead, tone: 'blue' as const, byBot: true },
            { label: t.tags.owner, tone: 'violet' as const, byBot: true },
        ],
        [{ label: t.tags.seeker, tone: 'cyan' as const, byBot: true }],
        [{ label: t.tags.billing, tone: 'amber' as const, byBot: true }],
        [{ label: t.tags.support, tone: 'slate' as const }],
    ];
    const handlers = [
        undefined,
        { kind: 'bot' as const, label: t.botHandles },
        {
            kind: 'agent' as const,
            name: t.agents.khalid,
            initials: lang === 'ar' ? 'خ' : 'K',
        },
        {
            kind: 'agent' as const,
            name: t.agents.sara,
            initials: lang === 'ar' ? 'س' : 'S',
        },
    ];

    return (
        <Section id="inbox" title={sections.inbox} lead={t.lead}>
            <div className="max-w-md overflow-hidden rounded-2xl border bg-card">
                <div className="flex gap-1 border-b p-2">
                    {t.tabs.map((tab, index) => (
                        <span
                            key={tab}
                            className={cn(
                                'rounded-full px-3 py-1.5 text-body-sm',
                                index === 0
                                    ? 'bg-primary font-semibold text-primary-foreground'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {tab}
                        </span>
                    ))}
                </div>
                <div className="flex flex-col gap-0.5 p-2">
                    {t.items.map((item, index) => (
                        <ConversationItem
                            key={item.name}
                            name={item.name}
                            initials={item.initials}
                            preview={item.preview}
                            time={item.time}
                            unread={'unread' in item ? item.unread : 0}
                            unreadLabel={
                                'unread' in item
                                    ? t.unread(item.unread)
                                    : undefined
                            }
                            waitingForPerson={'waiting' in item}
                            tags={tagsFor[index]}
                            handler={handlers[index]}
                            selected={selected === index}
                            closed={index === 3}
                            onClick={() => setSelected(index)}
                        />
                    ))}
                </div>
            </div>
        </Section>
    );
}

function DirectionSection({ lang }: { lang: Lang }) {
    const t = COPY[lang].direction;
    const sections = COPY[lang].sections;

    return (
        <Section id="direction" title={sections.direction} lead={t.lead}>
            <Panel className="flex flex-wrap items-center gap-3">
                <Button variant="outline">
                    <BackArrow />
                    {t.back}
                </Button>
                <Button>
                    {t.next}
                    <ForwardArrow />
                </Button>
                <Button variant="link" className="ms-auto">
                    {t.viewAll}
                    <ForwardChevron />
                </Button>
            </Panel>
        </Section>
    );
}
