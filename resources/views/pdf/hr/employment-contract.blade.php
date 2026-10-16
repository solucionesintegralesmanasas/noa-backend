@extends('pdf.layouts.base')

@section('title', $doc['title'] ?? 'Contrato Laboral')

@section('content')
<style>
    .contract-container {
        font-family: Arial, sans-serif;
        font-size: 11pt;
        line-height: 1.5;
        text-align: justify;
        color: #333;
    }
    .contract-title {
        text-align: center;
        font-weight: bold;
        font-size: 14pt;
        margin-bottom: 20px;
        text-transform: uppercase;
    }
    .contract-intro {
        margin-bottom: 20px;
    }
    .contract-clause {
        margin-bottom: 15px;
    }
    .contract-clause-title {
        font-weight: bold;
        margin-right: 5px;
    }
    .contract-signatures {
        margin-top: 50px;
        width: 100%;
        page-break-inside: avoid;
    }
    .signature-block {
        width: 45%;
        display: inline-block;
        vertical-align: top;
        margin-top: 30px;
    }
    .signature-line {
        border-top: 1px solid #000;
        width: 90%;
        margin-bottom: 5px;
    }
    .signature-name {
        font-weight: bold;
    }
    .signature-role {
        font-style: italic;
        font-size: 10pt;
    }
</style>

<div class="contract-container">
    <div class="contract-title">
        {{ $doc['title'] }}
    </div>

    <div class="contract-intro">
        {!! nl2br(e($doc['intro'])) !!}
    </div>

    <div class="contract-clauses">
        @foreach($doc['clauses'] as $clause)
            <div class="contract-clause">
                <span class="contract-clause-title">{{ $clause['title'] }}:</span>
                <span class="contract-clause-text">{!! nl2br(e($clause['text'])) !!}</span>
            </div>
        @endforeach
    </div>

    @if(isset($doc['closing']))
        <div class="contract-clause" style="margin-top: 30px;">
            {!! nl2br(e($doc['closing'])) !!}
        </div>
    @endif

    <div class="contract-signatures">
        @if(isset($doc['signatures']['employer']))
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-name">EL EMPLEADOR</div>
                <div class="signature-name">{{ $doc['signatures']['employer']['name'] ?? '' }}</div>
                @if(isset($doc['signatures']['employer']['id']))
                    <div>{{ $doc['signatures']['employer']['id'] }}</div>
                @endif
                @if(isset($doc['signatures']['employer']['role']))
                    <div class="signature-role">{{ $doc['signatures']['employer']['role'] }}</div>
                @endif
            </div>
        @endif

        @if(isset($doc['signatures']['employee']))
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-name">EL TRABAJADOR</div>
                <div class="signature-name">{{ $doc['signatures']['employee']['name'] ?? '' }}</div>
                @if(isset($doc['signatures']['employee']['id']))
                    <div>{{ $doc['signatures']['employee']['id'] }}</div>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
