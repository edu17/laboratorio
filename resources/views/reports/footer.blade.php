    <footer id="report-footer" class="validation report-footer">
        @if ($report['is_preview'] ?? false)
            <table class="report-assets"><tbody><tr>
                <td class="signature-cell">Área de firma del técnico<br>(sin aplicar)</td>
                <td class="seal-cell">Área de logo y sello</td>
                <td class="signature-cell">Área de firma de la química bióloga<br>(sin aplicar)</td>
            </tr></tbody></table>
            <p><strong>BORRADOR DE CATÁLOGO — SIN VALIDACIÓN CLÍNICA</strong></p>
        @endif
        @if (filled($report['institution']['technical_signature_data_uri']) || filled($report['institution']['biologist_signature_data_uri']) || filled($report['institution']['institutional_seal_data_uri']) || filled($report['institution']['logo_data_uri']))
            <table class="report-assets">
                <tbody>
                    <tr>
                        <td class="signature-cell">
                            @if (filled($report['institution']['technical_signature_data_uri']))
                                <img class="signature-image" src="{{ $report['institution']['technical_signature_data_uri'] }}" alt="Firma del técnico laboratorista">
                                <p class="signature-label">Técnico laboratorista</p>
                            @endif
                        </td>
                        <td class="seal-cell">
                            @if (filled($report['institution']['logo_data_uri']))
                                <img class="footer-logo" src="{{ $report['institution']['logo_data_uri'] }}" alt="Logotipo institucional al pie">
                            @endif
                            @if (filled($report['institution']['institutional_seal_data_uri']))
                                <img class="seal-image" src="{{ $report['institution']['institutional_seal_data_uri'] }}" alt="Sello institucional">
                                <p class="seal-label">Sello institucional</p>
                            @endif
                        </td>
                        <td class="signature-cell">
                            @if (filled($report['institution']['biologist_signature_data_uri']))
                                <img class="signature-image" src="{{ $report['institution']['biologist_signature_data_uri'] }}" alt="Firma de la química bióloga">
                                <p class="signature-label">Química bióloga</p>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        @endif
        @if (! ($report['is_preview'] ?? false))
        <table class="validation-metadata">
            <tbody><tr>
                <td><strong>Validado por:</strong> {{ $report['validation']['validator_name'] }}<br><span class="validated-state">Validado</span></td>
                <td><strong>Fecha de validación:</strong><br>{{ $report['validation']['validated_at_display'] }}</td>
            </tr></tbody>
        </table>
        @endif
        <table class="contact-footer">
            <tbody>
                <tr>
                    <td>
                        <p><strong>Dirección:</strong> {{ $report['institution']['address'] ?: 'Sin registrar' }}</p>
                        <p><strong>Horario:</strong> {{ $report['institution']['opening_hours'] ?: 'Sin registrar' }}</p>
                        @if (filled($report['institution']['phone']) || filled($report['institution']['email']))
                            <p>{{ collect([$report['institution']['phone'], $report['institution']['email']])->filter()->implode(' · ') }}</p>
                        @endif
                    </td>
                    <td class="emergency"><strong>Emergencias</strong><br>{{ $report['institution']['emergency_phone'] ?: 'Sin registrar' }}</td>
                </tr>
            </tbody>
        </table>
        <p class="version">Versión {{ $report['validation']['version'] }} · Formato {{ $report['template_version'] }} · Generado {{ $report['generated_at'] }}</p>
    </footer>
