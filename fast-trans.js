/**
 * TG-AuTheme.js v1.0.0
 * مكتبة مخصصة لمحاكاة تجربة مستخدم تيليجرام (Telegram UI)
 * مخصصة للمطور محمود - تصميم "دمار شامل"
 */

(function() {
    // 1. حقن التنسيقات (CSS) تلقائياً عند تحميل المكتبة
    const css = `
        :root {
            --tg-theme-bg: #17212b;
            --tg-theme-button: #2481cc;
            --tg-theme-button-text: #ffffff;
            --tg-ripple-color: rgba(255, 255, 255, 0.25);
            --tg-transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* تأثير الزر الأساسي */
        .tg-btn {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            border-radius: 10px;
            border: none;
            background-color: var(--tg-theme-button);
            color: var(--tg-theme-text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            overflow: hidden;
            transition: var(--tg-transition);
            user-select: none;
            outline: none;
            -webkit-tap-highlight-color: transparent;
        }

        /* تأثير التصغير عند الضغط (Scale Down) */
        .tg-btn:active {
            transform: scale(0.95);
        }

        /* تأثير الـ Ripple (الموجة) */
        .tg-ripple-effect {
            position: absolute;
            border-radius: 50%;
            background-color: var(--tg-ripple-color);
            transform: scale(0);
            animation: tg-ripple-animation 0.5s ease-out;
            pointer-events: none;
        }

        @keyframes tg-ripple-animation {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }

        /* ستايل البطاقة (Card) */
        .tg-card {
            background: #242f3d;
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            color: white;
            border: 0.5px solid rgba(255,255,255,0.05);
        }

        /* تأثيرات إضافية "دمار شامل" */
        .tg-glass {
            background: rgba(36, 47, 61, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
    `;

    const styleSheet = document.createElement("style");
    styleSheet.innerText = css;
    document.head.appendChild(styleSheet);

    // 2. منطق المكتبة (JavaScript)
    const TG_AuTheme = {
        init: function() {
            this.setupRipple();
            this.setupHaptics();
            this.logStatus();
        },

        // إنشاء تأثير الموجة
        setupRipple: function() {
            document.addEventListener('mousedown', (e) => {
                const btn = e.target.closest('.tg-btn');
                if (!btn) return;

                const ripple = document.createElement('span');
                ripple.classList.add('tg-ripple-effect');

                const rect = btn.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;

                ripple.style.width = ripple.style.height = `${size}px`;
                ripple.style.left = `${x}px`;
                ripple.style.top = `${y}px`;

                btn.appendChild(ripple);

                ripple.addEventListener('animationend', () => ripple.remove());
            });

            // دعم اللمس للهواتف
            document.addEventListener('touchstart', (e) => {
                const btn = e.target.closest('.tg-btn');
                if (btn && window.navigator.vibrate) {
                    window.navigator.vibrate(8); // اهتزاز خفيف جداً لمحاكاة النظام
                }
            }, { passive: true });
        },

        // محاكاة اهتزاز النظام (للأجهزة المتوافقة)
        setupHaptics: function() {
            document.addEventListener('click', (e) => {
                if (e.target.closest('.tg-btn')) {
                    // يمكنك إضافة صوت نقرة خفيف هنا إذا أردت
                }
            });
        },

        logStatus: function() {
            console.log(
                "%c TG-AuTheme Loaded 🚀 %c Created for Mahmoud ",
                "background: #2481cc; color: #fff; padding: 5px; border-radius: 5px 0 0 5px;",
                "background: #17212b; color: #3b82f6; padding: 5px; border-radius: 0 5px 5px 0;"
            );
        }
    };

    // التشغيل التلقائي
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => TG_AuTheme.init());
    } else {
        TG_AuTheme.init();
    }

    // إتاحة المكتبة عالمياً للاستدعاء اليدوي إذا لزم الأمر
    window.TG_AuTheme = TG_AuTheme;
})();
