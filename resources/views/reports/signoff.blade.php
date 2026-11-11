        <section class="report-signoff">
            <table class="signoff-box">
                <tbody>
                    <tr>
                        @if ($isPreview)
                            <td class="validation-cell"><span class="label">Borrador de catálogo</span><strong>Sin validación clínica</strong></td>
                            <td class="signature-cell"><p class="placeholder-cell">Firma del técnico (sin aplicar)</p></td>
                            <td class="signature-cell"><p class="placeholder-cell">Firma de la química bióloga (sin aplicar)</p></td>
                            <td class="seal-cell"><p class="placeholder-cell">Sello</p></td>
                        @else
                            <td class="validation-cell">
                                <span class="label">Validado electrónicamente</span>
                                <strong>{{ $report['validation']['validator_name'] }}</strong><br>
                                {{ $report['validation']['validated_at_display'] }} · Versión {{ $report['validation']['version'] }}<br>
                                <span class="validated-state">Validado</span>
                            </td>
                            <td class="signature-cell">
                                @if (filled($report['institution']['technical_signature_data_uri']))
                                    <img class="signature-image" src="{{ $report['institution']['technical_signature_data_uri'] }}" alt="Firma del técnico laboratorista">
                                    <p class="signature-label">Técnico laboratorista</p>
                                @endif
                            </td>
                            <td class="signature-cell">
                                @if (filled($report['institution']['biologist_signature_data_uri']))
                                    <img class="signature-image" src="{{ $report['institution']['biologist_signature_data_uri'] }}" alt="Firma de la química bióloga">
                                    <p class="signature-label">Química bióloga</p>
                                @endif
                            </td>
                            <td class="seal-cell">
                                @if (filled($report['institution']['institutional_seal_data_uri']))
                                    <img class="seal-image" src="{{ $report['institution']['institutional_seal_data_uri'] }}" alt="Sello institucional">
                                    <p class="seal-label">Sello institucional</p>
                                @endif
                            </td>
                        @endif
                    </tr>
                </tbody>
            </table>
            <p class="end-marker">— Fin del reporte —</p>
        </section>
