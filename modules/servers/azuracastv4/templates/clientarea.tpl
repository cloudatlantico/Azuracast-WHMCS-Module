{if $error}
    <div class="azc-alert-error">{$error}</div>
{else}
    <style>
        .azc-client-area {
            --azc-bg: linear-gradient(155deg, #eef6ff 0%, #f7fbff 48%, #edf4ff 100%);
            --azc-card: rgba(255, 255, 255, 0.88);
            --azc-border: rgba(96, 165, 250, 0.22);
            --azc-text: #1e3a5f;
            --azc-muted: #6b8bb3;
            --azc-primary: #3b82f6;
            --azc-success: #22c55e;
            --azc-danger: #ef4444;
            color: var(--azc-text);
            background: var(--azc-bg);
            border-radius: 16px;
            padding: 26px;
            font-family: Inter, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            box-shadow: 0 16px 34px rgba(59, 130, 246, 0.14);
        }

        .azc-alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 12px 14px;
            font-weight: 600;
        }

        .azc-header {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .azc-title {
            margin: 0;
            font-size: 24px;
            line-height: 1.2;
            color: #18406f;
            letter-spacing: .2px;
        }

        .azc-subtitle {
            margin: 2px 0 0;
            color: var(--azc-muted);
            font-size: 13px;
        }

        .azc-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            border: 1px solid var(--azc-border);
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.68);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: #345b86;
        }

        .azc-status-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--azc-danger);
            box-shadow: 0 0 0 5px rgba(239, 68, 68, 0.12);
        }

        .azc-status-dot.online {
            background: var(--azc-success);
            box-shadow: 0 0 0 5px rgba(34, 197, 94, 0.14);
        }

        .azc-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .azc-card {
            background: var(--azc-card);
            border: 1px solid var(--azc-border);
            border-radius: 14px;
            padding: 18px;
            backdrop-filter: blur(5px);
        }

        .azc-card h3 {
            margin: 0 0 14px;
            font-size: 16px;
            color: #18406f;
        }

        .azc-list {
            display: grid;
            gap: 10px;
        }

        .azc-item {
            border-radius: 10px;
            border: 1px solid rgba(96, 165, 250, 0.20);
            background: rgba(248, 252, 255, 0.95);
            padding: 11px 12px;
        }

        .azc-item-label {
            display: block;
            font-size: 11px;
            color: var(--azc-muted);
            text-transform: uppercase;
            letter-spacing: .35px;
            margin-bottom: 4px;
        }

        .azc-item-value {
            font-size: 14px;
            color: var(--azc-text);
            word-break: break-word;
        }

        .azc-item-value a {
            color: #2563eb;
            text-decoration: none;
        }

        .azc-item-value a:hover {
            text-decoration: underline;
        }

        .azc-cred-line {
            display: block;
            margin-bottom: 6px;
        }

        .azc-cred-line strong {
            color: #345b86;
            font-weight: 600;
        }

        .azc-secret {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .azc-secret-toggle {
            border: 1px solid rgba(96, 165, 250, 0.35);
            background: #ffffff;
            color: #1d4ed8;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all .12s ease;
        }

        .azc-secret-toggle:hover {
            background: #eff6ff;
        }

        .azc-secret-copy {
            cursor: pointer;
            border-radius: 8px;
            padding: 2px 6px;
            background: rgba(37, 99, 235, 0.08);
            border: 1px dashed rgba(37, 99, 235, 0.22);
            transition: background .12s ease, border-color .12s ease;
        }

        .azc-secret-copy:hover {
            background: rgba(37, 99, 235, 0.14);
            border-color: rgba(37, 99, 235, 0.38);
        }

        .azc-secret-copy.copied {
            color: #15803d;
            border-color: rgba(34, 197, 94, 0.45);
            background: rgba(34, 197, 94, 0.10);
        }

        .azc-secret-hint {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            color: var(--azc-muted);
        }

        .azc-login-btn {
            display: inline-block;
            margin-top: 6px;
            text-decoration: none;
            padding: 10px 14px;
            border-radius: 10px;
            background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%);
            color: #fff !important;
            font-weight: 600;
            box-shadow: 0 8px 16px rgba(59, 130, 246, 0.35);
            transition: transform .12s ease, box-shadow .12s ease;
        }

        .azc-login-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 18px rgba(79, 70, 229, 0.40);
        }

        @media (max-width: 991px) {
            .azc-grid { grid-template-columns: 1fr; }
            .azc-client-area { padding: 18px; }
        }
    </style>

    <script>
        (function () {
            function maskSecret(value) {
                var text = String(value || '');
                return text.length ? '••••••••••••' : '-';
            }

            function copyText(text) {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    return navigator.clipboard.writeText(text);
                }

                return new Promise(function (resolve, reject) {
                    var input = document.createElement('input');
                    input.value = text;
                    document.body.appendChild(input);
                    input.select();
                    try {
                        document.execCommand('copy');
                        resolve();
                    } catch (e) {
                        reject(e);
                    }
                    document.body.removeChild(input);
                });
            }

            function bindSecrets() {
                var secrets = document.querySelectorAll('.azc-secret-copy');
                secrets.forEach(function (secret) {
                    if (secret.dataset.bound === '1') {
                        return;
                    }
                    secret.dataset.bound = '1';

                    var rawValue = secret.getAttribute('data-secret') || '';
                    secret.textContent = maskSecret(rawValue);

                    secret.addEventListener('click', function () {
                        var hidden = secret.getAttribute('data-revealed') !== '1';

                        if (hidden) {
                            secret.textContent = rawValue || '-';
                            secret.setAttribute('data-revealed', '1');
                        }

                        copyText(rawValue).then(function () {
                            var original = secret.textContent;
                            secret.classList.add('copied');
                            secret.textContent = 'Copiado!';
                            setTimeout(function () {
                                secret.classList.remove('copied');
                                secret.textContent = secret.getAttribute('data-revealed') === '1' ? (rawValue || '-') : maskSecret(rawValue);
                            }, 1200);
                        }).catch(function () {
                            // Mantém o valor revelado mesmo se não copiar.
                        });
                    });
                });

                var toggles = document.querySelectorAll('.azc-secret-toggle');
                toggles.forEach(function (toggle) {
                    if (toggle.dataset.bound === '1') {
                        return;
                    }
                    toggle.dataset.bound = '1';

                    toggle.addEventListener('click', function () {
                        var targetId = toggle.getAttribute('data-target');
                        var target = document.getElementById(targetId);
                        if (!target) {
                            return;
                        }

                        var rawValue = target.getAttribute('data-secret') || '';
                        var revealed = target.getAttribute('data-revealed') === '1';
                        if (revealed) {
                            target.textContent = maskSecret(rawValue);
                            target.setAttribute('data-revealed', '0');
                            toggle.textContent = 'Revelar';
                        } else {
                            target.textContent = rawValue || '-';
                            target.setAttribute('data-revealed', '1');
                            toggle.textContent = 'Ocultar';
                        }
                    });
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bindSecrets);
            } else {
                bindSecrets();
            }
        })();
    </script>

    <div class="azc-client-area">
        <div class="azc-header">
            <div>
                <h2 class="azc-title">{$station.name}</h2>
                <p class="azc-subtitle">{$labels.station_information|default:'Informações da Rádio'}</p>
            </div>
            <div class="azc-status-badge">
                <span class="azc-status-dot {if $station.status == 'Online'}online{/if}"></span>
                {if $station.status == 'Online'}{$labels.online|default:'Online'}{else}{$labels.offline|default:'Offline'}{/if}
            </div>
        </div>

        <div class="azc-grid">
            <section class="azc-card">
                <h3>{$labels.station_information|default:'Informações da Rádio'}</h3>
                <div class="azc-list">
                    <div class="azc-item"><span class="azc-item-label">{$labels.station_name|default:'Nome da Estação'}</span><span class="azc-item-value">{$station.name}</span></div>
                    <div class="azc-item"><span class="azc-item-label">{$labels.frontend|default:'Servidor de Transmissão'}</span><span class="azc-item-value">{$station.frontend_type|upper}</span></div>
                    <div class="azc-item"><span class="azc-item-label">{$labels.public_page|default:'Página Pública'}</span><span class="azc-item-value">{if $station.public_url != '-'}<a href="{$station.public_url}" target="_blank" rel="noopener">{$station.public_url}</a>{else}-{/if}</span></div>
                    <div class="azc-item"><span class="azc-item-label">{$labels.stream_port|default:'Porta de Transmissão'}</span><span class="azc-item-value">{$station.station_port}</span></div>
                    <div class="azc-item"><span class="azc-item-label">{$labels.autodj_port|default:'Porta AutoDJ'}</span><span class="azc-item-value">{$station.autodj_port}</span></div>
                    <div class="azc-item"><span class="azc-item-label">{$labels.max_bitrate|default:'Bitrate Máximo'}</span><span class="azc-item-value">{$station.max_bitrate} kbps</span></div>
                    <div class="azc-item"><span class="azc-item-label">{$labels.max_listeners|default:'Ouvintes Máximos'}</span><span class="azc-item-value">{$station.max_listeners}</span></div>
                    <div class="azc-item"><span class="azc-item-label">{$labels.disk_quota|default:'Quota de Disco'}</span><span class="azc-item-value">{$station.disk_quota}</span></div>
                </div>
            </section>

            <section class="azc-card">
                <h3>{$labels.access_information|default:'Informações de Acesso'}</h3>
                <div class="azc-list">
                    <div class="azc-item">
                        <span class="azc-item-label">{$labels.frontend_admin|default:'Administração'}</span>
                        <span class="azc-item-value">
                            <span class="azc-cred-line"><strong>{$labels.username|default:'Usuário'}:</strong> {$station.admin_username}</span>
                            <span class="azc-cred-line"><strong>{$labels.password|default:'Senha'}:</strong> <span class="azc-secret"><span id="admin-password-secret" class="azc-secret-copy" data-secret="{$station.admin_password|escape:'html'}" data-revealed="0">••••••••••••</span><button type="button" class="azc-secret-toggle" data-target="admin-password-secret">Revelar</button></span><span class="azc-secret-hint">Clique na senha para copiar.</span></span>
                        </span>
                    </div>
                    <div class="azc-item">
                        <span class="azc-item-label">{$labels.frontend_source|default:'Fonte'}</span>
                        <span class="azc-item-value">
                            <span class="azc-cred-line"><strong>{$labels.username|default:'Usuário'}:</strong> {$station.source_username}</span>
                            <span class="azc-cred-line"><strong>{$labels.password|default:'Senha'}:</strong> <span class="azc-secret"><span id="source-password-secret" class="azc-secret-copy" data-secret="{$station.source_password|escape:'html'}" data-revealed="0">••••••••••••</span><button type="button" class="azc-secret-toggle" data-target="source-password-secret">Revelar</button></span><span class="azc-secret-hint">Clique na senha para copiar.</span></span>
                        </span>
                    </div>
                    <div class="azc-item">
                        <span class="azc-item-label">{$labels.frontend_relay|default:'Retransmissão'}</span>
                        <span class="azc-item-value">
                            <span class="azc-cred-line"><strong>{$labels.username|default:'Usuário'}:</strong> {$station.relay_username}</span>
                            <span class="azc-cred-line"><strong>{$labels.password|default:'Senha'}:</strong> <span class="azc-secret"><span id="relay-password-secret" class="azc-secret-copy" data-secret="{$station.relay_password|escape:'html'}" data-revealed="0">••••••••••••</span><button type="button" class="azc-secret-toggle" data-target="relay-password-secret">Revelar</button></span><span class="azc-secret-hint">Clique na senha para copiar.</span></span>
                        </span>
                    </div>
                    <div class="azc-item"><span class="azc-item-label">{$labels.dj_username|default:'Usuário de Acesso'}</span><span class="azc-item-value">{$station.dj_username}</span></div>
                    <div class="azc-item"><span class="azc-item-label">{$labels.dj_password|default:'Senha de Acesso'}</span><span class="azc-item-value"><span class="azc-secret"><span id="dj-password-secret" class="azc-secret-copy" data-secret="{$station.dj_password|escape:'html'}" data-revealed="0">••••••••••••</span><button type="button" class="azc-secret-toggle" data-target="dj-password-secret">Revelar</button></span><span class="azc-secret-hint">Clique na senha para copiar.</span></span></div>
                    <div class="azc-item">
                        <span class="azc-item-label">{$labels.direct_login|default:'Acessar Painel do Servidor'}</span>
                        <a href="{$station.login_url}" target="_blank" rel="noopener" class="azc-login-btn">{$labels.direct_login|default:'Acessar Painel do Servidor'}</a>
                    </div>
                </div>
            </section>
        </div>
    </div>
{/if}
