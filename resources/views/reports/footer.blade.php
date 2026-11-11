    @php($formatPhone = fn (?string $phone): ?string => is_string($phone) && preg_match('/\A\d{8}\z/', $phone) === 1 ? substr($phone, 0, 4).'-'.substr($phone, 4) : $phone)
    <footer id="report-footer" class="report-footer">
        <table class="contact-footer">
            <tbody>
                <tr>
                    <td>
                        <strong>Dirección:</strong> {{ $report['institution']['address'] ?: 'Sin registrar' }}<br>
                        <strong>Horario:</strong> {{ $report['institution']['opening_hours'] ?: 'Sin registrar' }}
                        @if (filled($report['institution']['phone']) || filled($report['institution']['email']))
                            · Tel. {{ collect([$formatPhone($report['institution']['phone']), $report['institution']['email']])->filter()->implode(' · ') }}
                        @endif
                    </td>
                    <td class="emergency"><strong>Emergencias</strong><br>{{ $formatPhone($report['institution']['emergency_phone']) ?: 'Sin registrar' }}</td>
                </tr>
            </tbody>
        </table>
        <table class="footer-meta">
            <tbody>
                <tr>
                    <td>Orden {{ $report['order']['number'] }} · Versión {{ $report['validation']['version'] }} · Formato {{ $report['template_version'] }} · Generado {{ $report['generated_at'] }}</td>
                    <td class="page-number"></td>
                </tr>
            </tbody>
        </table>
    </footer>
