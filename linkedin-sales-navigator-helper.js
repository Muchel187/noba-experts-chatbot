/**
 * NOBA EXPERTS - LinkedIn Sales Navigator Helper
 *
 * ⚠️ WICHTIG: Dieses Skript ist SEMI-AUTOMATISCH
 * - Es analysiert Profile und generiert Nachrichten
 * - Du musst MANUELL auf "Senden" klicken
 * - Vollautomatik würde gegen LinkedIn ToS verstoßen!
 *
 * INSTALLATION:
 * 1. Öffne LinkedIn Sales Navigator
 * 2. Öffne Chrome DevTools (F12)
 * 3. Gehe zu "Console"
 * 4. Kopiere dieses Skript und füge es ein
 * 5. Drücke Enter
 */

(function() {
    'use strict';

    // ===== KONFIGURATION =====
    const CONFIG = {
        // Deine Chatbot-URL
        chatbotUrl: 'https://chatbot.noba-experts.de',

        // Delay zwischen Aktionen (in ms) - um natürlich zu wirken
        minDelay: 2000,
        maxDelay: 5000,

        // Anzahl Nachrichten pro Session (nicht zu viele!)
        maxMessagesPerSession: 10,

        // Dein Name für die Nachrichten
        senderName: 'Jurak Bahrambaek',
        senderTitle: 'NOBA Experts GmbH'
    };

    // ===== NACHRICHTENVORLAGEN =====
    const MESSAGE_TEMPLATES = {
        // Variante 1: Problem-fokussiert
        recruiterPain: (profile) => `Hallo ${profile.firstName},

ich habe gesehen, dass Sie bei ${profile.company} im Recruiting tätig sind.

Die Kandidatensuche ist zeitaufwendig – besonders wenn Bewerber nicht genau wissen, welche Position zu ihnen passt oder unsicher sind, was sie eigentlich suchen.

Deshalb haben wir **Mina** entwickelt: Unsere KI-Recruiterin, die Kandidaten 24/7 vorqualifiziert, ihre Skills analysiert und passende Positionen vorschlägt – bevor sie überhaupt bei Ihnen landen.

Das Ergebnis: Qualifiziertere Gespräche, weniger Zeitverschwendung, mehr Treffer.

Neugierig? Hier können Sie Mina selbst testen: ${CONFIG.chatbotUrl}

Beste Grüße,
${CONFIG.senderName}
${CONFIG.senderTitle}`,

        // Variante 2: Benefit-fokussiert
        efficiency: (profile) => `Hallo ${profile.firstName},

was wäre, wenn Ihre Kandidaten bereits vorqualifiziert sind, bevor das erste Gespräch stattfindet?

**Mina** – unsere neue KI-Recruiterin – führt mit jedem Kandidaten ein erstes Gespräch:
✓ Analysiert Skills & Erfahrung
✓ Versteht Karriereziele
✓ Schlägt passende Positionen vor
✓ Arbeitet 24/7, auch nachts und am Wochenende

Das spart Ihnen Zeit bei der Vorauswahl und sorgt für bessere Matches.

Probieren Sie es aus: ${CONFIG.chatbotUrl}

Viele Grüße,
${CONFIG.senderName}
${CONFIG.senderTitle}`,

        // Variante 3: Innovation-fokussiert
        innovation: (profile) => `Hallo ${profile.firstName},

Hand aufs Herz: Wie viel Zeit verbringen Sie mit Kandidaten, die am Ende doch nicht passen?

Wir haben eine Lösung entwickelt, die genau das verhindert:

**Mina** – eine KI-Recruiterin, die Kandidaten in Echtzeit berät, ihre Fähigkeiten erfasst und nur wirklich passende Profile an Sie weiterleitet.

Das Besondere: Sie lernt mit jedem Gespräch dazu und wird immer präziser.

Überzeugen Sie sich selbst: ${CONFIG.chatbotUrl}

Mit besten Grüßen,
${CONFIG.senderName}
${CONFIG.senderTitle}`,

        // Variante 4: Direkt & kurz
        short: (profile) => `Hallo ${profile.firstName},

kurze Frage: Wie finden Sie die Idee einer KI, die Ihre Kandidaten vorqualifiziert – bevor Sie Zeit investieren?

**Mina**, unsere neue KI-Recruiterin, macht genau das. 24/7.

Einfach mal testen: ${CONFIG.chatbotUrl}

Grüße,
${CONFIG.senderName}`,

        // Variante 5: Daten-fokussiert
        datadriven: (profile) => `Hallo ${profile.firstName},

bei ${profile.company} haben Sie sicher täglich mit einer Flut von Bewerbungen zu tun.

**Mina** hilft Ihnen dabei, schneller die richtigen Kandidaten zu identifizieren:

→ Automatische Skill-Analyse
→ Kulturfit-Einschätzung (Big Five-Modell)
→ 24/7 Kandidaten-Vorqualifizierung
→ Nur qualifizierte Leads landen bei Ihnen

Das spart Zeit und erhöht die Trefferquote signifikant.

Live testen: ${CONFIG.chatbotUrl}

Viele Grüße,
${CONFIG.senderName}
${CONFIG.senderTitle}`
    };

    // ===== PROFIL-ANALYSE =====
    function analyzeProfile() {
        try {
            // Versuche Name zu extrahieren
            const nameElement = document.querySelector('.artdeco-entity-lockup__title') ||
                               document.querySelector('[data-anonymize="person-name"]') ||
                               document.querySelector('.mn-connection-card__name');

            const name = nameElement ? nameElement.textContent.trim() : 'dort';
            const firstName = name.split(' ')[0] || 'dort';

            // Versuche Company zu extrahieren
            const companyElement = document.querySelector('.artdeco-entity-lockup__subtitle') ||
                                  document.querySelector('[data-anonymize="company-name"]') ||
                                  document.querySelector('.mn-connection-card__occupation');

            const company = companyElement ? companyElement.textContent.trim() : 'Ihrem Unternehmen';

            // Versuche Headline zu extrahieren
            const headlineElement = document.querySelector('.artdeco-entity-lockup__caption') ||
                                   document.querySelector('[data-anonymize="headline"]');

            const headline = headlineElement ? headlineElement.textContent.trim() : '';

            // Prüfe ob HR-bezogen
            const isHR = /recruiter|recruiting|hr|human resources|talent|people|personalwesen/i.test(headline + ' ' + company);

            return {
                name,
                firstName,
                company,
                headline,
                isHR,
                confidence: (name !== 'dort' && company !== 'Ihrem Unternehmen') ? 'high' : 'low'
            };
        } catch (error) {
            console.error('Fehler bei Profilanalyse:', error);
            return {
                name: 'dort',
                firstName: 'dort',
                company: 'Ihrem Unternehmen',
                headline: '',
                isHR: true,
                confidence: 'low'
            };
        }
    }

    // ===== NACHRICHTEN-GENERATOR =====
    function generateMessage(profile) {
        // Wähle Template basierend auf Zufallsprinzip (für Variation)
        const templates = Object.values(MESSAGE_TEMPLATES);
        const randomTemplate = templates[Math.floor(Math.random() * templates.length)];

        return randomTemplate(profile);
    }

    // ===== HELPER PANEL UI =====
    function createHelperPanel() {
        // Prüfe ob Panel bereits existiert
        if (document.getElementById('noba-helper-panel')) {
            return;
        }

        const panel = document.createElement('div');
        panel.id = 'noba-helper-panel';
        panel.innerHTML = `
            <style>
                #noba-helper-panel {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    width: 400px;
                    max-height: 80vh;
                    background: white;
                    border: 2px solid #ff7b29;
                    border-radius: 12px;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                    z-index: 999999;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                }

                #noba-helper-header {
                    background: #ff7b29;
                    color: white;
                    padding: 16px;
                    font-weight: 600;
                    font-size: 16px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }

                #noba-helper-close {
                    background: none;
                    border: none;
                    color: white;
                    font-size: 24px;
                    cursor: pointer;
                    padding: 0;
                    width: 30px;
                    height: 30px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 50%;
                    transition: background 0.2s;
                }

                #noba-helper-close:hover {
                    background: rgba(255,255,255,0.2);
                }

                #noba-helper-body {
                    padding: 20px;
                    overflow-y: auto;
                    flex: 1;
                }

                .noba-profile-info {
                    background: #f8f9fa;
                    padding: 12px;
                    border-radius: 8px;
                    margin-bottom: 16px;
                    font-size: 14px;
                }

                .noba-profile-info strong {
                    color: #333;
                }

                .noba-message-preview {
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    padding: 16px;
                    margin-bottom: 16px;
                    font-size: 14px;
                    line-height: 1.6;
                    white-space: pre-wrap;
                    max-height: 300px;
                    overflow-y: auto;
                }

                .noba-action-buttons {
                    display: flex;
                    gap: 10px;
                    margin-top: 16px;
                }

                .noba-btn {
                    flex: 1;
                    padding: 12px;
                    border: none;
                    border-radius: 8px;
                    font-weight: 600;
                    font-size: 14px;
                    cursor: pointer;
                    transition: all 0.2s;
                }

                .noba-btn-primary {
                    background: #ff7b29;
                    color: white;
                }

                .noba-btn-primary:hover {
                    background: #e66a1f;
                }

                .noba-btn-secondary {
                    background: #f0f0f0;
                    color: #333;
                }

                .noba-btn-secondary:hover {
                    background: #e0e0e0;
                }

                .noba-stats {
                    background: #e3f2fd;
                    padding: 12px;
                    border-radius: 8px;
                    margin-bottom: 16px;
                    font-size: 13px;
                    color: #1565c0;
                }

                .noba-warning {
                    background: #fff3cd;
                    border-left: 4px solid #ffc107;
                    padding: 12px;
                    margin-bottom: 16px;
                    font-size: 13px;
                    border-radius: 4px;
                }
            </style>

            <div id="noba-helper-header">
                <span>🤖 NOBA Mina - LinkedIn Helper</span>
                <button id="noba-helper-close">×</button>
            </div>

            <div id="noba-helper-body">
                <div class="noba-warning">
                    ⚠️ <strong>Wichtig:</strong> Du musst manuell auf "Senden" klicken! Vollautomatik verstößt gegen LinkedIn ToS.
                </div>

                <div class="noba-stats" id="noba-stats">
                    📊 Session: 0/${CONFIG.maxMessagesPerSession} Nachrichten
                </div>

                <div class="noba-profile-info" id="noba-profile-info">
                    <strong>Profil wird analysiert...</strong>
                </div>

                <div class="noba-message-preview" id="noba-message-preview">
                    Klicke auf "Profil analysieren" um zu starten...
                </div>

                <div class="noba-action-buttons">
                    <button class="noba-btn noba-btn-secondary" id="noba-analyze-btn">
                        🔍 Profil analysieren
                    </button>
                    <button class="noba-btn noba-btn-secondary" id="noba-regenerate-btn" style="display:none;">
                        🔄 Neue Variante
                    </button>
                    <button class="noba-btn noba-btn-primary" id="noba-copy-btn" style="display:none;">
                        📋 Kopieren & Öffnen
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(panel);

        // Event Listeners
        document.getElementById('noba-helper-close').addEventListener('click', () => {
            panel.remove();
        });

        let currentProfile = null;
        let messagesSent = 0;

        document.getElementById('noba-analyze-btn').addEventListener('click', () => {
            currentProfile = analyzeProfile();
            const message = generateMessage(currentProfile);

            document.getElementById('noba-profile-info').innerHTML = `
                <strong>Name:</strong> ${currentProfile.name}<br>
                <strong>Firma:</strong> ${currentProfile.company}<br>
                <strong>HR-Rolle:</strong> ${currentProfile.isHR ? '✅ Ja' : '❌ Nein'}<br>
                <strong>Zuversicht:</strong> ${currentProfile.confidence === 'high' ? '🟢 Hoch' : '🟡 Mittel'}
            `;

            document.getElementById('noba-message-preview').textContent = message;
            document.getElementById('noba-regenerate-btn').style.display = 'block';
            document.getElementById('noba-copy-btn').style.display = 'block';
        });

        document.getElementById('noba-regenerate-btn').addEventListener('click', () => {
            if (currentProfile) {
                const message = generateMessage(currentProfile);
                document.getElementById('noba-message-preview').textContent = message;
            }
        });

        document.getElementById('noba-copy-btn').addEventListener('click', () => {
            const message = document.getElementById('noba-message-preview').textContent;

            // Kopiere in Zwischenablage
            navigator.clipboard.writeText(message).then(() => {
                // Update Button
                const btn = document.getElementById('noba-copy-btn');
                btn.textContent = '✅ Kopiert!';
                setTimeout(() => {
                    btn.innerHTML = '📋 Kopieren & Öffnen';
                }, 2000);

                // Update Stats
                messagesSent++;
                document.getElementById('noba-stats').innerHTML = `
                    📊 Session: ${messagesSent}/${CONFIG.maxMessagesPerSession} Nachrichten
                    ${messagesSent >= CONFIG.maxMessagesPerSession ? '<br>⚠️ <strong>Limit erreicht! Mach eine Pause.</strong>' : ''}
                `;

                // Versuche Nachrichtenfeld zu öffnen
                tryOpenMessageDialog();
            }).catch(err => {
                alert('Fehler beim Kopieren: ' + err);
            });
        });
    }

    // ===== VERSUCHE LINKEDIN-NACHRICHTENFELD ZU ÖFFNEN =====
    function tryOpenMessageDialog() {
        try {
            // Versuche "Message" Button zu finden
            const messageButton = document.querySelector('[data-control-name="message"]') ||
                                 document.querySelector('button[aria-label*="Message"]') ||
                                 document.querySelector('.artdeco-button--primary[aria-label*="Message"]');

            if (messageButton) {
                messageButton.click();
                console.log('✅ Nachrichtenfeld geöffnet - füge die Nachricht manuell ein!');
            } else {
                console.log('ℹ️ Nachrichtenfeld konnte nicht automatisch geöffnet werden. Öffne es manuell.');
            }
        } catch (error) {
            console.log('ℹ️ Nachrichtenfeld konnte nicht geöffnet werden:', error);
        }
    }

    // ===== INITIALISIERUNG =====
    console.log('%c🤖 NOBA LinkedIn Helper geladen!', 'color: #ff7b29; font-size: 16px; font-weight: bold;');
    console.log('%c✅ Panel wird angezeigt...', 'color: #4caf50; font-size: 14px;');

    createHelperPanel();

    // Keyboard Shortcut: Ctrl+Shift+M öffnet Panel
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey && e.shiftKey && e.key === 'M') {
            if (!document.getElementById('noba-helper-panel')) {
                createHelperPanel();
            }
        }
    });

})();
