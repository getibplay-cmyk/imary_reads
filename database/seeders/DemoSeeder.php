<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Category;
use App\Models\Post;
use App\Models\Work;
use App\Services\ChapterWriter;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Demo data cannot be seeded in production.');
        }
        $this->call(DatabaseSeeder::class);
        $author = Author::firstOrCreate(['slug' => 'imary-stories'], ['name' => 'حكايات Imary', 'bio' => 'نصوص أصلية توضيحية لاختبار تجربة القراءة. الشخصيات والأحداث خيالية.']);
        $works = [['asdaa-marrakech', 'أصداء مراكش', 'history', 'hero.webp', 'في أزقة المدينة الحمراء، يعثر ياسين على دفتر قديم في متجر للكتب. تقوده صفحاته إلى رحلة لاكتشاف معنى الذاكرة والحفاظ على حكايات المكان.'], ['zilal-andalus', 'ظل الأندلس', 'literature', 'andalus.webp', 'بين أشجار البرتقال وأروقة المكتبة، تبدأ رحلة بحث عن رسالة مفقودة. حكاية عن الكتب التي تجمع بين الأزمنة، وعن أثر الكلمات في حياة أصحابها.'], ['rihla-sahra', 'رحلة في الصحراء', 'travel', 'desert.webp', 'تنطلق قافلة صغيرة إلى واحة بعيدة تحت سماء لا تنتهي. في صمت الرمال، يتعلم مسافر شاب كيف ينصت إلى الأرض ويعرف قيمة الرفقة.'], ['mamlakat-khayal', 'مملكة الخيال', 'fantasy', 'fantasy.webp', 'فوق جبال تحيط بها الغيوم، تختبئ مدينة تضيء نوافذها كلما رويت حكاية صادقة. يحاول حارس المكتبة جمع قصص أهلها قبل أن تنطفئ آخر نافذة.'], ['bayna-sotour', 'بين السطور', 'novels', 'hero.webp', 'في مكتبة هادئة، يلتقي دفتر بلا عنوان بقارئ يكتب ملاحظاته على الهوامش. قصة عن اكتشاف الشغف، وعن البدايات التي لا تحتاج إلى أكثر من صفحة.'], ['ward-fes', 'ورد من فاس', 'heritage', 'andalus.webp', 'يعود مرمم كتب إلى فاس ليعيد فتح مكتبة العائلة. بين الورق المعتق ونقوش الأبواب، يجد حكايات تستحق أن تعود إلى القراء.']];
        $paragraphs = [
            'كانت المدينة تستيقظ على مهل. تسللت خيوط الضوء بين الأبواب الخشبية، ورسمت على الزليج أشكالًا صغيرة تشبه نوافذ مفتوحة على زمن آخر. وقف ياسين أمام مكتبة الحي، يتأمل حروف اللافتة التي بهت لونها، ويتساءل كم حكاية عبرت هذا الباب قبل أن يصل إليه.',
            'دفع الباب برفق، فسمع رنين جرس نحاسي. في الداخل، امتدت الرفوف حتى السقف، وازدحمت بعناوين مختلفة الأحجام والألوان. لم تكن المكتبة كبيرة، لكنها بدت أوسع كلما تقدم بين رفوفها. كان لكل كتاب مكان، ولكل مكان ذكرى لا يعرفها إلا من اعتنى به سنوات طويلة.',
            'على الطاولة القريبة من النافذة وجد دفترًا ملفوفًا بقماش أزرق. لم يحمل غلافه اسمًا ولا تاريخًا. فتح الصفحة الأولى فقرأ جملة قصيرة: ليست المدن حجارة فحسب، بل ما يتذكره الناس حين ينطقون أسماءها. أعاد قراءة العبارة، ثم جلس كما لو أنه وجد أخيرًا السؤال الذي كان يبحث عنه.',
            'جاء صاحب المكتبة بكوب ماء، ووضعه إلى جانب الدفتر. قال إن الأوراق وصلت مع صندوق من الكتب القديمة، وإن أحدًا لم يسأل عنها حتى اليوم. لم يعرض بيع الدفتر، بل طلب أن يعود إليه بعد القراءة، فقد اعتاد أن يترك للحكايات وقتًا كافيًا كي تختار أصحابها.',
            'في الخارج، كان الحرفيون يفتحون دكاكينهم. صوت مطرقة بعيد، وخطوات على حجارة مصقولة، ورائحة خبز يحملها هواء الصباح. أخذ ياسين يدوّن ما يراه، لا ليصف المدينة كما تظهر في الصور، بل كما يشعر بها من يمشي ببطء وينتبه إلى التفاصيل التي تعبر عادة دون أن نلاحظها.',
            'وجد بين الصفحات رسمًا لخريطة صغيرة. لم تكن فيها أسماء الشوارع، بل إشارات إلى شجرة ونافورة وباب أخضر. قرر أن يتبعها بعد الظهيرة. وحين طوى الدفتر، أدرك أن الرحلة بدأت قبل أن يخطو خارج المكتبة، وأن الأشياء البسيطة قادرة أحيانًا على تغيير اتجاه يوم كامل.',
            'عند المنعطف الأخير توقّف أمام نافورة جف ماؤها منذ زمن. كانت الشجرة ما تزال هناك، تُلقي ظلها على مقعد حجري. جلس قليلًا، ثم لاحظ على الجدار نقشًا يشبه العلامة الموجودة في الدفتر. لم يجد كنزًا ولا سرًا عجيبًا، بل أسماء أشخاص ساهموا في بناء هذا المكان ليكون استراحة لكل عابر.',
            'عاد مع الغروب وهو يحمل حكايات سمعها من أهل الحي. في المكتبة، فتح صفحة جديدة وكتب ما تعلّمه: لا نحفظ ذاكرة الأماكن بإغلاقها على الماضي، بل بمنحها حياة يستطيع القادمون المشاركة فيها. ثم ترك القلم إلى جانب الدفتر، وأفسح مكانًا للقارئ التالي.',
        ];
        foreach ($works as $i => [$slug,$title,$category,$cover,$synopsis]) {
            $work = Work::firstOrCreate(['slug' => $slug], ['author_id' => $author->id, 'category_id' => Category::where('slug', $category)->firstOrFail()->id, 'title' => $title, 'synopsis' => $synopsis, 'language' => 'ar', 'kind' => 'novel', 'status' => 'published', 'completion' => $i % 2 ? 'ongoing' : 'completed', 'cover_asset' => $cover, 'license' => 'permission', 'rights_note' => 'نصوص أصلية توضيحية أُعدت للمشروع؛ ليست مؤلفات منقولة أو أعمالًا لمؤلفين حقيقيين.', 'rights_confirmed' => true, 'allow_download' => true, 'allow_comments' => true, 'featured' => true, 'published_at' => now()->subDays(6 - $i)]);
            if (! $work->chapters()->exists()) {
                foreach (['لقاء في المدينة', 'أسرار الصفحات', 'العودة إلى الحكاية'] as $j => $heading) {
                    $parts = array_merge(array_slice($paragraphs, $j), array_slice($paragraphs, 0, $j));
                    app(ChapterWriter::class)->save($work, ['title' => $heading, 'content' => '<p>'.implode('</p><p>', $parts).'</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
                }
            }
        }
        Post::firstOrCreate(['slug' => 'welcome'], ['title' => ['ar' => 'أهلًا بك في Imary Reads', 'fr' => 'Bienvenue sur Imary Reads', 'en' => 'Welcome to Imary Reads'], 'body' => ['ar' => '<p>اختر حكاية من المكتبة، وافتح وضع الكتاب لتقليب الصفحات. يمكنك تغيير الخط والمظهر وحفظ موضعك بعد تسجيل الدخول.</p>', 'fr' => '<p>Choisissez un livre et ouvrez le mode livre pour tourner les pages. Adaptez le texte et le thème, puis connectez-vous pour enregistrer votre progression.</p>', 'en' => '<p>Choose a book and open book mode to turn the pages. Adjust text and theme, and sign in to save your place.</p>'], 'status' => 'published', 'published_at' => now()]);
    }
}
