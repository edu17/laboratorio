    @php($formatPhone = fn (?string $phone): ?string => is_string($phone) && preg_match('/\A\d{8}\z/', $phone) === 1 ? substr($phone, 0, 4).'-'.substr($phone, 4) : $phone)
    <footer id="report-footer" class="report-footer">
        <table class="contact-footer">
            <tbody>
                <tr>
                    <td>
                        {{ $report['institution']['address'] ?: 'Dirección sin registrar' }}
                        · {{ $report['institution']['opening_hours'] ?: 'Horario sin registrar' }}<br>
                        @if (filled($report['institution']['phone']))
                            Tel. {{ $formatPhone($report['institution']['phone']) }} ·
                        @endif
                        Emergencias {{ $formatPhone($report['institution']['emergency_phone']) ?: 'sin registrar' }}
                        @if (filled($report['institution']['email']))
                            · {{ $report['institution']['email'] }}
                        @endif
                    </td>
                    <td class="footer-meta">
                        {{ $report['order']['number'] }} · Formato {{ $report['template_version'] }} · Generado {{ $report['generated_at'] }}<br>
                        &nbsp;
                    </td>
                </tr>
            </tbody>
        </table>
    </footer>
