import { Head } from '@inertiajs/react';
import type { CSSProperties } from 'react';

import { MirsalinkWordmark } from '@/components/brand/mirsalink-logo';
import { CategoryTag } from '@/components/inbox/category-tag';
import { ConversationItem } from '@/components/inbox/conversation-item';
import { MessageBubble } from '@/components/inbox/message-bubble';
import { DirectionContext } from '@/hooks/use-direction';

/*
 * A side-by-side of font pairings for the interface, each set on the real
 * inbox components by overriding --font-sans and --font-display on its card.
 * The fonts load from Google here only; the chosen pair is then self-hosted
 * through vite.config.ts like the current one.
 */

type Pairing = {
    id: string;
    title: string;
    ui: string;
    display: string;
    why: string;
    watch: string;
    current?: boolean;
};

const PAIRINGS: Pairing[] = [
    {
        id: 'plex',
        title: 'هادئ وتقني',
        ui: 'IBM Plex Sans Arabic',
        display: 'Readex Pro',
        why: 'هادئ وتقني، وواضح جدًا في القوائم الكثيفة. حروفه اللاتينية من العائلة نفسها.',
        watch: 'شخصيته محايدة، فلا يضيف إلى الهوية كثيرًا.',
    },
    {
        id: 'alexandria',
        title: 'هندسي معاصر',
        ui: 'Vazirmatn',
        display: 'Alexandria',
        why: 'Vazirmatn من أوضح الخطوط العربية في الأحجام الصغيرة، وAlexandria للعناوين هندسي حديث صُمّم عربيًا ولاتينيًا معًا.',
        watch: 'Alexandria عريض، فيُستخدم للعناوين فقط.',
    },
    {
        id: 'rubik',
        title: 'مستدير وودود',
        ui: 'Rubik',
        display: 'Rubik',
        why: 'زواياه المستديرة تشبه طرفي العصوين في الشعار، وعائلة واحدة للغتين بكل الأوزان.',
        watch: 'أقل رسمية، وقد يبدو خفيفًا لعملاء الشركات الكبيرة.',
    },
    {
        id: 'almarai',
        title: 'سعودي مألوف',
        ui: 'Almarai',
        display: 'Almarai',
        why: 'صُمّم لعلامة سعودية، ومألوف للعين في المملكة. واضح ومتوازن وجاد.',
        watch: 'أربعة أوزان فقط (300 و400 و700 و800)، فلا وزن متوسطًا بين العادي والعريض.',
        current: true,
    },
    {
        id: 'tajawal',
        title: 'خفيف وأنيق',
        ui: 'Tajawal',
        display: 'Alexandria',
        why: 'Tajawal رشيق ومنتشر في المواقع الخليجية، ومعه Alexandria يعطي العناوين حضورًا.',
        watch: 'حروف Tajawal صغيرة نسبيًا، فيحتاج مقاس النص إلى زيادة بكسل.',
    },
    {
        id: 'readex',
        title: 'عائلة واحدة للقراءة',
        ui: 'Readex Pro',
        display: 'Readex Pro',
        why: 'صُمّم للوضوح وسهولة القراءة، وحروفه اللاتينية من Lexend. شخصية واضحة بعائلة واحدة.',
        watch: 'عريض قليلًا، فتتسع القوائم ويقل ما يظهر منها في الشاشة.',
    },
    {
        id: 'noto',
        title: 'كوفي للعناوين',
        ui: 'Noto Sans Arabic',
        display: 'Noto Kufi Arabic',
        why: 'نص محايد ومتين، وعناوين كوفية تعطي المنصة طابعًا عربيًا واضحًا.',
        watch: 'الكوفي ثقيل في العناوين الطويلة، فيُكتفى به للقصيرة.',
    },
];

const FAMILIES = Array.from(
    new Set(PAIRINGS.flatMap((pairing) => [pairing.ui, pairing.display])),
);

const GOOGLE_FONTS_URL = `https://fonts.googleapis.com/css2?${FAMILIES.map(
    (family) =>
        `family=${family.replaceAll(' ', '+')}${family === 'Almarai' ? ':wght@400;700;800' : ':wght@400;500;600;700'}`,
).join('&')}&display=swap`;

export default function DesignSystemFonts() {
    return (
        <DirectionContext.Provider value="rtl">
            <Head title="خطوط الواجهة">
                <link rel="stylesheet" href={GOOGLE_FONTS_URL} />
            </Head>
            <div dir="rtl" lang="ar" className="min-h-screen bg-background">
                <header className="border-b bg-card">
                    <div className="mx-auto flex max-w-7xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-4 sm:px-6">
                        <MirsalinkWordmark className="text-xl" />
                        <h1 className="font-display text-title font-semibold">
                            خطوط الواجهة
                        </h1>
                        <p className="w-full text-body-sm text-muted-foreground">
                            سبعة أزواج من الخطوط على عناصر المنصة نفسها. في كل
                            زوج خط للواجهة والنصوص، وخط للعناوين والأرقام
                            الكبيرة. اسم المنصة في الشعار يبقى بخطّه كما هو.
                        </p>
                    </div>
                </header>

                <main className="mx-auto grid max-w-7xl gap-5 px-4 py-8 sm:px-6 md:grid-cols-2 xl:grid-cols-3">
                    {PAIRINGS.map((pairing) => (
                        <PairingCard key={pairing.id} pairing={pairing} />
                    ))}
                </main>
            </div>
        </DirectionContext.Provider>
    );
}

function PairingCard({ pairing }: { pairing: Pairing }) {
    const fonts = {
        '--font-sans': `'${pairing.ui}', sans-serif`,
        '--font-display': `'${pairing.display}', sans-serif`,
    } as CSSProperties;

    return (
        <article
            style={fonts}
            className="flex flex-col overflow-hidden rounded-2xl border bg-card font-sans"
        >
            <div className="flex flex-col gap-1 border-b px-5 py-4">
                <div className="flex items-center gap-2">
                    <h2 className="font-display text-heading font-semibold">
                        {pairing.title}
                    </h2>
                    {pairing.current && (
                        <span className="rounded-full bg-primary px-2.5 py-0.5 text-caption font-semibold text-primary-foreground">
                            المستخدم الآن
                        </span>
                    )}
                </div>
                <p
                    dir="ltr"
                    className="text-start font-mono text-caption text-muted-foreground"
                >
                    {pairing.ui === pairing.display
                        ? pairing.ui
                        : `${pairing.ui} · ${pairing.display}`}
                </p>
            </div>

            <div className="flex flex-col gap-1 px-5 pt-5">
                <p className="font-display text-display leading-tight font-bold">
                    صندوق الوارد
                </p>
                <p className="font-display text-title text-muted-foreground">
                    <span className="tabular">12</span> محادثة بانتظار الفريق
                </p>
            </div>

            <div className="flex flex-col gap-0.5 px-2 pt-3">
                <ConversationItem
                    name="عبدالله الحربي"
                    initials="ع"
                    preview="عندنا 40 موظف. ممكن أكلم أحد من المبيعات؟"
                    time="10:05"
                    unread={2}
                    waitingForPerson
                    tags={[
                        { label: 'عميل محتمل', tone: 'blue', byBot: true },
                        { label: 'صاحب شركة', tone: 'violet', byBot: true },
                    ]}
                />
                <ConversationItem
                    name="مريم القحطاني"
                    initials="م"
                    preview="المساعد: تقدرين ترفعين السيرة الذاتية من حسابك."
                    time="09:48"
                    handler={{ kind: 'bot', label: 'المساعد' }}
                />
            </div>

            <div className="flex flex-col gap-3 bg-sunken px-4 py-4">
                <MessageBubble
                    kind="agent"
                    author="سارة"
                    time="10:07"
                    status={{ status: 'read', label: 'قُرئت' }}
                >
                    أهلًا عبدالله، معك سارة من فريق المبيعات. جهّزت لك عرضًا يناسب
                    40 موظفًا، وفيه خصم السنة الأولى.
                </MessageBubble>
            </div>

            <div className="flex flex-col gap-3 px-5 py-4">
                <p className="text-body">
                    يصنّف المساعد كل محادثة ويضع وسمها، ثم يسلّمها للموظف المناسب
                    في الوقت المناسب.
                </p>
                <p dir="ltr" className="text-start text-body">
                    The assistant tags every conversation and hands it to the
                    right person.
                </p>
                <div className="flex flex-wrap items-center gap-2">
                    <CategoryTag tone="amber">دفع وفوترة</CategoryTag>
                    <span
                        dir="ltr"
                        className="font-mono text-body-sm text-muted-foreground"
                    >
                        +966 55 123 4567
                    </span>
                </div>
            </div>

            <dl className="mt-auto grid gap-2 border-t px-5 py-4 text-body-sm">
                <div>
                    <dt className="font-semibold">ميزته</dt>
                    <dd className="text-muted-foreground">{pairing.why}</dd>
                </div>
                <div>
                    <dt className="font-semibold">انتبه إلى</dt>
                    <dd className="text-muted-foreground">{pairing.watch}</dd>
                </div>
            </dl>
        </article>
    );
}
