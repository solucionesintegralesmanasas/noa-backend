{{-- Estado de evidencia diario: separación entre cierre operativo y certificación. --}}
@if (!empty($evidencia_incompleta))
    <div class="banda-evidencia">
        <strong>EVIDENCIA OPERATIVA INCOMPLETA.</strong>
        Este documento tiene datos o firmas operativas pendientes. No debe confundirse
        con la firma administrativa de certificación.
        @foreach (($firmas_pendientes ?? []) as $diaIncompleto)
            <div class="banda-evidencia-detalle">
                &bull; {{ $diaIncompleto['fecha'] }}: {{ implode(' · ', $diaIncompleto['pendientes']) }}
            </div>
        @endforeach
    </div>
@endif

@if (!empty($certificaciones_pendientes))
    <div class="banda-evidencia banda-certificacion">
        <strong>PENDIENTE DE CERTIFICACIÓN ADMINISTRATIVA.</strong>
        La operación está cerrada; falta la firma del coordinador. Esta firma puede incorporarse después.
        <div class="banda-evidencia-detalle">
            &bull; Días: {{ implode(', ', $certificaciones_pendientes) }}
        </div>
    </div>
@endif

@if (!empty($dias_con_excepcion))
    <div class="banda-evidencia banda-excepcion">
        <strong>CERRADA CON EXCEPCIÓN.</strong>
        Algún día no pudo completarse por motivos justificados y fue aprobado por
        un administrador; no equivale a un día con evidencia completa.
        @foreach ($dias_con_excepcion as $diaExc)
            <div class="banda-evidencia-detalle">
                &bull; {{ $diaExc['fecha'] }}: {{ $diaExc['motivo'] }}
            </div>
        @endforeach
    </div>
@endif
