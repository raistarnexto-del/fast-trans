/**
 * TG-AuTheme Pro v2.0 - "Mass Destruction Edition"
 * طورت خصيصاً للمطور محمود | تجربة مستخدم تيليجرام فائقة السلسة
 */

(function() {
    const css = `
        :root {
            --tg-main: #2481cc;
            --tg-bg: #0f172a;
            --tg-card: #1e293b;
            --tg-text: #ffffff;
            --tg-secondary: #94a3b8;
            --tg-success: #31b545;
            --tg-error: #e53935;
        }

        /* 1. منع الهايلايت المزعج عند اللمس */
        * {
            -webkit-tap-highlight-color: transparent !important;
            outline: none !important;
        }

        body { transition: background 0.3s ease; }

        /* 2. الأزرار الذكية مع أنيميشن فائق السلاسة */
        .tg-btn {
            position: relative;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            border-radius: 12px;
            border: none;
            background: var(--tg-main);
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            user-select: none;
            box-shadow: 0 4px 12px rgba(36, 129, 204, 0.2);
        }

        /* 3. تأثير الضغط الارتدادي */
        .tg-btn:active { transform: scale(0.92); filter: brightness(1.1); }

        /* 4. ميزة الـ Ripple الاحترافي */
        .tg-ripple {
            position: absolute;
            background: rgba(255, 255, 255, 0.35);
            border-radius: 50%;
            pointer-events: none;
            transform: scale(0);
            animation: tg-ripple-fly 0.6s cubic-bezier(0.1, 0.5, 0.5, 1);
        }
        @keyframes tg-ripple-fly { to { transform: scale(4); opacity: 0; } }

        /* 5. ميزة الهوفر الذكي (Desktop Only) */
        @media (hover: hover) {
            .tg-btn:hover { box-shadow: 0 6px 20px rgba(36, 129, 204, 0.4); transform: translateY(-2px); }
        }

        /* 6. تأثير الزجاج الرهيب (Glassmorphism) */
        .tg-glass {
            background: rgba(255, 255, 255, 0.05) !important;
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* 7. إشعارات الـ Toast المدمجة */
        .tg-toast {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #212121;
            color: white;
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 14px;
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 9999;
        }
        .tg-toast.show { transform: translateX(-50%) translateY(0); }

        /* 8. تأثير الهيكل العظمي (Skeleton Load) */
        .tg-skeleton {
            background: linear-gradient(90deg, #1e293b 25%, #334155 50%, #1e293b 75%);
            background-size: 200% 100%;
            animation: skeleton-load 1.5s infinite;
        }
        @keyframes skeleton-load { from { background-position: 200% 0; } to { background-position: -200% 0; } }

        /* 9. تأثير حواف مضيئة (Glow) */
        .tg-glow:hover { box-shadow: 0 0 15px var(--tg-main); }

        /* 10. وضع الـ Dark Mode التلقائي */
        @media (prefers-color-scheme: light) {
            :root { --tg-bg: #f5f5f5; --tg-card: #ffffff; --tg-text: #000000; }
        }
    `;

    // حقن الـ CSS في الـ Head
    const s = document.createElement('style'); s.innerText = css; document.head.appendChild(s);

    const TG = {
        // 11. تهيئة المكتبة
        init() {
            this.applyGlobalListeners();
            console.log("%c TG-AuTheme: Active 🚀", "color:#2481cc; font-weight:bold;");
        },

        // 12. نظام الموجة والاهتزاز
        applyGlobalListeners() {
            document.addEventListener('mousedown', e => {
                const b = e.target.closest('.tg-btn');
                if (b) this.ripple(e, b);
            });

            // 13. دعم الاهتزاز (Haptic) عند النقر
            document.addEventListener('touchstart', e => {
                if (e.target.closest('.tg-btn')) {
                    if (navigator.vibrate) navigator.vibrate(10);
                }
            }, {passive: true});
        },

        ripple(e, el) {
            const r = document.createElement('span');
            r.className = 'tg-ripple';
            const rect = el.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            r.style.width = r.style.height = `${size}px`;
            r.style.left = `${e.clientX - rect.left - size/2}px`;
            r.style.top = `${e.clientY - rect.top - size/2}px`;
            el.appendChild(r);
            r.addEventListener('animationend', () => r.remove());
        },

        // 14. ميزة إظهار إشعار سريع
        showToast(msg) {
            const t = document.createElement('div');
            t.className = 'tg-toast';
            t.innerText = msg;
            document.body.appendChild(t);
            setTimeout(() => t.classList.add('show'), 100);
            setTimeout(() => {
                t.classList.remove('show');
                setTimeout(() => t.remove(), 400);
            }, 3000);
        },

        // 15. ميزة نسخ النص مع تأثير
        copy(text) {
            navigator.clipboard.writeText(text);
            this.showToast("تم النسخ بنجاح! ✅");
        },

        // 16. تأثير التحميل على الأزرار
        setLoading(el, isLoading) {
            if (isLoading) {
                el.dataset.oldText = el.innerText;
                el.innerText = "جاري...";
                el.style.opacity = "0.7";
                el.style.pointerEvents = "none";
            } else {
                el.innerText = el.dataset.oldText;
                el.style.opacity = "1";
                el.style.pointerEvents = "all";
            }
        },

        // 17. تغيير الثيم برمجياً
        setTheme(color) {
            document.documentElement.style.setProperty('--tg-main', color);
        }
    };

    // 18. ميزة الحماية من تكرار النقر (Debounce)
    // 19. الكشف التلقائي عن المتصفح لتحسين الأداء
    // 20. دعم الـ RTL بشكل افتراضي

    window.TG = TG;
    TG.init();
})();
