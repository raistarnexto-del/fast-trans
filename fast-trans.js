(function() {
    const FastTrans = {
        userLang: (navigator.language || navigator.userLanguage).split('-')[0],
        apiEndpoint: "https://translate.googleapis.com/translate_a/single?client=gtx&sl=auto&tl=",

        async init() {
            // تجنب الترجمة إذا كانت لغة الصفحة هي نفس لغة المستخدم
            const docLang = document.documentElement.lang || '';
            if (docLang.toLowerCase().includes(this.userLang)) return;

            const walker = document.createTreeWalker(
                document.body,
                NodeFilter.SHOW_TEXT,
                {
                    acceptNode: function(node) {
                        const parent = node.parentElement.tagName.toLowerCase();
                        if (['script', 'style', 'noscript', 'code', 'pre'].includes(parent)) {
                            return NodeFilter.FILTER_REJECT;
                        }
                        return node.textContent.trim().length > 1 ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
                    }
                }
            );

            let node;
            const nodes = [];
            const texts = [];

            while (node = walker.nextNode()) {
                nodes.push(node);
                texts.push(node.textContent.trim());
            }

            if (texts.length === 0) return;

            // تجميع كل النصوص وفصلها بعلامة خاصة عشان نبعتهم طلب واحد
            const combinedText = texts.join(' ||| ');
            this.translateAll(nodes, combinedText, this.userLang);
        },

        async translateAll(nodes, combinedText, targetLang) {
            try {
                const response = await fetch(`${this.apiEndpoint}${targetLang}&dt=t&q=${encodeURIComponent(combinedText)}`);
                const data = await response.json();
                
                if (data && data[0]) {
                    // جوجل بيرجع النص المترجم مجمع
                    const fullTranslated = data[0].map(item => item[0]).join('');
                    // نفك التجميع تاني ونوزع النصوص على العناصر
                    const translatedArray = fullTranslated.split(' ||| ');

                    nodes.forEach((node, index) => {
                        if (translatedArray[index]) {
                            node.textContent = translatedArray[index].trim();
                        }
                    });
                }
            } catch (error) {
                console.error("FastTrans Error:", error);
            }
        }
    };

    window.addEventListener('load', () => FastTrans.init());
})();
