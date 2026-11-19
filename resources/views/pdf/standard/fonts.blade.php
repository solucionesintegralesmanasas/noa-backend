{{-- Tipografía estándar de los PDF: Inter v4.1 estática y local (sin descargas remotas).
     Única fuente de las @font-face: la incluyen pdf.standard.styles y las plantillas
     que conservan su propia maquetación (ficha del conductor, planilla de control). --}}
@php
    $__fontDir = str_replace('\\', '/', resource_path('fonts/inter'));
@endphp
<style>
        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 400;
            src: url('{{ $__fontDir }}/Inter-Regular.ttf') format('truetype');
        }

        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 500;
            src: url('{{ $__fontDir }}/Inter-Medium.ttf') format('truetype');
        }

        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 600;
            src: url('{{ $__fontDir }}/Inter-SemiBold.ttf') format('truetype');
        }

        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 700;
            src: url('{{ $__fontDir }}/Inter-Bold.ttf') format('truetype');
        }

        @font-face {
            font-family: 'Inter';
            font-style: italic;
            font-weight: 400;
            src: url('{{ $__fontDir }}/Inter-Italic.ttf') format('truetype');
        }
</style>
