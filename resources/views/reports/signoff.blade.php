        <section class="report-signoff">
            <table class="end-marker">
                <tbody>
                    <tr>
                        <td class="rule"></td>
                        <td class="text">Fin del reporte</td>
                        <td class="rule"></td>
                    </tr>
                </tbody>
            </table>

            @if ($isPreview)
                <table class="report-assets">
                    <tbody>
                        <tr>
                            <td class="signature-cell"><p class="placeholder-cell">Área de firma del técnico (sin aplicar)</p></td>
                            <td class="seal-cell"><p class="placeholder-cell">Área de sello</p></td>
                            <td class="signature-cell"><p class="placeholder-cell">Área de firma de la química bióloga (sin aplicar)</p></td>
                        </tr>
                    </tbody>
                </table>
                <p class="preview-notice" style="margin-top: 2mm;"><strong>BORRADOR DE CATÁLOGO — SIN VALIDACIÓN CLÍNICA</strong></p>
            @else
                <table class="validation-metadata">
                    <tbody>
                        <tr>
                            <td>
                                <strong>Validado por:</strong> {{ $report['validation']['validator_name'] }}
                                · <strong>Fecha de validación:</strong> {{ $report['validation']['validated_at_display'] }}
                            </td>
                            <td><span class="validated-state">Validado</span></td>
                        </tr>
                    </tbody>
                </table>

                @if (filled($report['institution']['technical_signature_data_uri']) || filled($report['institution']['biologist_signature_data_uri']) || filled($report['institution']['institutional_seal_data_uri']))
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
            @endif
        </section>
