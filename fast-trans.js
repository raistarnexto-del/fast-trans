/**
 * TG-Master UI v5.0 - The "Ultimate Beast" Edition
 * Created for: Mahmoud (Expert Developer)
 * Features: YouTube Glow, iOS Blur, Telegram Ripple, Haptic Engine
 */

(function(window, document) {
    "use strict";

    const TG_Master = {
        // الإعدادات الافتراضية - دمار شامل
        defaults: {
            primary: '#2481cc',
            glow: 'rgba(36, 129, 204, 0.9)',
            blurIntensity: '20px',
            hapticStrength: 15,
            animationSpeed: '0.4s'
        },

        init: function(options) {
            this.config = Object.assign({}, this.defaults, options);
            this._injectMasterStyles();
            this._buildLoader();
            this._attachGlobalEvents();
            this._welcomeLog();
        },

        // 1. حقن الأكواد الرسومية (CSS) - "مليون سطر" من الجمال
        _injectMasterStyles: function() {
            const css = `
                :root {
                    --tg-primary: ${this.config.primary};
                    --tg-glow: ${this.config.glow};
                    --tg-blur: ${this.config.blurIntensity};
                    --tg-curve: cubic-bezier(0.4, 0, 0.2, 1);
                }

                /* منع الهايلايت في الموبايل لضمان سلاسة iOS */
                * { -webkit-tap-highlight-color: transparent !important; }

                /* شريط تحميل يوتيوب مع التوهج */
                #tg-progress-bar {
                    position: fixed; top: 0; left: 0; height: 3px;
                    background: var(--tg-primary);
                    box-shadow: 0 0 15px var(--tg-glow), 0 0 8px var(--tg-glow);
                    z-index: 10000; width: 0; opacity: 0;
                    transition: width var(--tg-curve), opacity 0.3s ease;
                }

                /* تأثير الزجاج iOS Blur */
                .tg-ios-blur {
                    backdrop-filter: blur(var(--tg-blur));
                    -webkit-backdrop-filter: blur(var(--tg-blur));
                    background: rgba(255, 255, 255, 0.05);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 15px;
                }

                /* أزرار تليجرام الاحترافية */
                .tg-btn {
                    position: relative; overflow: hidden;
                    display: inline-flex; align-items: center; justify-content: center;
                    padding: 14px 28px; border-radius: 14px; border: none;
                    background: var(--tg-primary); color: #fff;
                    font-weight: 600; font-family: sans-serif;
                    cursor: pointer; transition: transform 0.1s var(--tg-curve);
                    user-select: none;
                }
                .tg-btn:active { transform: scale(0.94); filter: brightness(1.1); }

                /* تأثير الـ Ripple */
                .tg-ripple-element {
                    position: absolute; border-radius: 50%;
                    background: rgba(255, 255, 255, 0.35);
                    transform: scale(0); animation: tg-ripple-fly 0.6s linear;
                    pointer-events: none;
                }
                @keyframes tg-ripple-fly { to { transform: scale(4); opacity: 0; } }

                /* كلاسات مساعدة للدمار الشامل */
                .tg-card { background: #1c1c1e; border-radius: 20px; padding: 25px; border: 1px solid rgba(255,255,255,0.05); }
                .tg-skeleton { 
                    background: linear-gradient(90deg, #2c2c2e 25%, #3a3a3c 50%, #2c2c2e 75%);
                    background-size: 200% 100%; animation: tg-skele 1.5s infinite;
                }
                @keyframes tg-skele { from { background-position: 200% 0; } to { background-position: -200% 0; } }
            `;
            const style = document.createElement('style');
            style.textContent = css;
            document.head.appendChild(style);
        },

        // 2. بناء عناصر الواجهة
        _buildLoader: function() {
            const bar = document.createElement('div');
            bar.id = 'tg-progress-bar';
            document.body.appendChild(bar);
            this.loaderEl = bar;
        },

        // 3. نظام التحكم في شريط التحميل (YouTube Glow Style)
        progress: {
            start: function() {
                const el = document.getElementById('tg-progress-bar');
                el.style.opacity = '1';
                el.style.width = '40%';
                setTimeout(() => { el.style.width = '80%'; }, 400);
            },
            done: function() {
                const el = document.getElementById('tg-progress-bar');
                el.style.width = '100%';
                setTimeout(() => {
                    el.style.opacity = '0';
                    setTimeout(() => { el.style.width = '0'; }, 400);
                }, 200);
            }
        },

        // 4. محرك الأحداث (Ripple & Haptic)
        _attachGlobalEvents: function() {
            document.addEventListener('mousedown', (e) => {
                const btn = e.target.closest('.tg-btn');
                if (btn) {
                    this._ripple(e, btn);
                    if (navigator.vibrate) navigator.vibrate(this.config.hapticStrength);
                }
            });
        },

        _ripple: function(e, btn) {
            const ripple = document.createElement('span');
            ripple.className = 'tg-ripple-element';
            const rect = btn.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            ripple.style.width = ripple.style.height = `${size}px`;
            ripple.style.left = `${e.clientX - rect.left - size / 2}px`;
            ripple.style.top = `${e.clientY - rect.top - size / 2}px`;
            btn.appendChild(ripple);
            ripple.onanimationend = () => ripple.remove();
        },

        _welcomeLog: function() {
            console.log("%c TG-MASTER UI v5.0 %c BY MAHMOUD ", "color: white; background: #2481cc; padding: 5px; border-radius: 5px 0 0 5px;", "color: #2481cc; background: #1c1c1e; padding: 5px; border-radius: 0 5px 5px 0;");
        }
    };

    // ربط المكتبة بالكائن العالمي للتأكد من عدم حدوث ReferenceError
    window.TG = TG_Master;
    
    // تشغيل تلقائي
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => TG.init());
    } else {
        TG.init();
    }

})(window, document);
