/**
 * Z-UI.js v1.0.0 - "The Absolute Beast Edition"
 * تم التطوير خصيصاً للمطور محمود | محاكاة Telegram UI و iOS Blur
 * ميزات: دمار شامل، سلاسة فائقة، تخصيص ألوان بضغطة زر، تأثير زجاجي iOS
 */

(function() {
    const ZUI = {
        // الإعدادات الافتراضية (Default Config)
        config: {
            primary: '#2481cc',      // لون تليجرام الأزرق
            background: '#17212b',    // خلفية داكنة
            card: '#242f3d',          // بطاقات داكنة
            text: '#ffffff',          // نص أبيض
            rippleOpacity: 0.3,       // شفافية تأثير الموجة
            blurStrength: '15px'      // قوة تأثير الـ Blur (iOS Style)
        },

        // 1. تهيئة المكتبة بحقن التنسيقات والمنطق
        init: function(userConfig = {}) {
            // دمج إعدادات المستخدم مع الافتراضية
            this.config = { ...this.config, ...userConfig };
            this._injectCSS();
            this._setupEventListeners();
            this._setupSkeletonLoader();
            console.log("%c Z-UI Beast: Active 🚀 %c (iOS Blur + Telegram Ripple) ", 
                "color:#2481cc; font-weight:bold; font-size: 1.1rem;",
                "color:#ffffff; background:#17212b; padding: 2px 5px; border-radius:4px;");
        },

        // 2. حقن كل الـ CSS الجبار برمجياً
        _injectCSS: function() {
            const c = this.config;
            const css = `
                :root {
                    --z-primary: ${c.primary};
                    --z-bg: ${c.background};
                    --z-card: ${c.card};
                    --z-text: ${c.text};
                    --z-transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                }

                /* منع الهايلايت المزعج في الموبايل */
                * { -webkit-tap-highlight-color: transparent !important; outline: none !important; box-sizing: border-box; }

                body { background-color: var(--z-bg); color: var(--z-text); transition: background 0.3s ease; }

                /* === الأزرار (Telegram Style) === */
                .z-btn {
                    position: relative; overflow: hidden; display: inline-flex; align-items: center; justify-content: center;
                    padding: 12px 24px; border-radius: 12px; border: none; background: var(--z-primary);
                    color: white; font-weight: 600; cursor: pointer; user-select: none;
                    transition: var(--z-transition); box-shadow: 0 4px 10px rgba(0,0,0,0.1);
                }
                /* تأثير التصغير الارتدادي */
                .z-btn:active { transform: scale(0.95); filter: brightness(1.1); }
                .z-btn.secondary { background: rgba(255,255,255,0.08); color: var(--z-primary); }

                /* === تأثير الموجة (Ripple) === */
                .z-ripple {
                    position: absolute; background: rgba(255, 255, 255, ${c.rippleOpacity});
                    border-radius: 50%; pointer-events: none; transform: scale(0);
                    animation: z-ripple-anim 0.6s cubic-bezier(0, 0, 0.2, 1);
                }
                @keyframes z-ripple-anim { to { transform: scale(4); opacity: 0; } }

                /* === تأثير الزجاج iOS (True Blur) === */
                .z-glass {
                    background: rgba(255, 255, 255, 0.04) !important;
                    backdrop-filter: blur(${c.blurStrength});
                    -webkit-backdrop-filter: blur(${c.blurStrength});
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 20px;
                }
                /* نسخة داكنة من الزجاج */
                .z-glass-dark {
                    background: rgba(0, 0, 0, 0.5) !important;
                    backdrop-filter: blur(${c.blurStrength});
                    -webkit-backdrop-filter: blur(${c.blurStrength});
                    border: 1px solid rgba(255, 255, 255, 0.05);
                }

                /* === البطاقات (Cards) === */
                .z-card { background: var(--z-card); border-radius: 20px; padding: 20px; color: var(--z-text); border: 1px solid rgba(255,255,255,0.05); }

                /* === الهيكل العظمي (Skeleton Load) === */
                .z-skeleton {
                    background: linear-gradient(90deg, var(--z-card) 25%, rgba(255,255,255,0.05) 50%, var(--z-card) 75%);
                    background-size: 200% 100%; animation: z-skeleton-anim 1.5s infinite; border-radius: 8px;
                }
                @keyframes z-skeleton-anim { from { background-position: 200% 0; } to { background-position: -200% 0; } }
            `;
            const s = document.createElement('style'); s.innerText = css; document.head.appendChild(s);
        },

        // 3. إعداد مستمعي الأحداث (Logic)
        _setupEventListeners: function() {
            // تأثير الـ Ripple
            document.addEventListener('mousedown', e => {
                const btn = e.target.closest('.z-btn');
                if (btn) this._createRipple(e, btn);
            });

            // تأثير الاهتزاز (Haptic) للموبايل
            document.addEventListener('touchstart', e => {
                if (e.target.closest('.z-btn') && navigator.vibrate) navigator.vibrate(12);
            }, {passive: true});
        },

        // 4. دالة إنشاء الموجة
        _createRipple: function(e, btn) {
            const r = document.createElement('span');
            r.className = 'z-ripple';
            const rect = btn.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            r.style.width = r.style.height = `${size}px`;
            r.style.left = `${e.clientX - rect.left - size/2}px`;
            r.style.top = `${e.clientY - rect.top - size/2}px`;
            btn.appendChild(r);
            r.addEventListener('animationend', () => r.remove());
        },

        // 5. تهيئة الـ Skeleton Loader تلقائياً
        _setupSkeletonLoader: function() {
            document.querySelectorAll('[data-z-load]').forEach(el => {
                el.classList.add('z-skeleton');
                // مثال: data-z-load="h:20px; w:80%"
                const styles = el.dataset.zLoad.split(';');
                styles.forEach(s => {
                    const [prop, val] = s.split(':');
                    if (prop && val) el.style[prop.trim()] = val.trim();
                });
            });
        }
    };

    window.ZUI = ZUI;
})();
