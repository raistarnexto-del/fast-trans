(function() {
    const FastTrans = {
        // تحديد لغة جهاز المستخدم (مثل 'ar', 'en', 'fr')
        userLang: (navigator.language || navigator.userLanguage).split('-')[0],
        apiEndpoint: "https://translate.googleapis.com/translate_a/single?client=gtx&sl=auto&tl=",

        async init() {
            // تجنب الترجمة إذا كانت لغة الصفحة الأصلية هي نفس لغة المستخدم
            const docLang = document.documentElement.lang || 'auto';
            if (docLang.toLowerCase().includes(this.userLang)) return;

            // الحصول على جميع العناصر النصية داخل الـ Body
            // نستخدم مصفوفة لتجنب ترجمة الوسوم التقنية
            const walker = document.createTreeWalker(
                document.body,
                NodeFilter.SHOW_TEXT,
                {
                    acceptNode: function(node) {
                        const parent = node.parentElement.tagName.toLowerCase();
                        // تجاهل العناصر الحساسة التي قد تفسد الموقع إذا تُرجمت
                        if (['script', 'style', 'noscript', 'code', 'pre'].includes(parent)) {
                            return NodeFilter.FILTER_REJECT;
                        }
                        return node.textContent.trim().length > 1 ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
                    }
                }
            );

            let node;
            const textNodes = [];
            while (node = walker.nextNode()) {
                textNodes.push(node);
            }

            // ترجمة النصوص دفعة واحدة (أو على مجموعات لضمان السرعة)
            for (const textNode of textNodes) {
                this.translateNode(textNode, this.userLang);
            }
        },

        async translateNode(node, targetLang) {
            const originalText = node.textContent;
            
            try {
                const response = await fetch(`${this.apiEndpoint}${targetLang}&dt=t&q=${encodeURIComponent(originalText)}`);
                const data = await response.json();
                
                if (data && data[0]) {
                    // تجميع النص المترجم (في حال كان النص الأصلي طويلاً وجوجل قسمه)
                    const translatedText = data[0].map(item => item[0]).join('');
                    node.textContent = translatedText;
                }
            } catch (error) {
                // في حال حدوث خطأ، يظل النص الأصلي كما هو دون تأثر
                console.error("FastTrans API Error:", error);
            }
        }
    };

    // التشغيل التلقائي بمجرد استدعاء المكتبة
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => FastTrans.init());
    } else {
        FastTrans.init();
    }

    window.FastTrans = FastTrans;
})();