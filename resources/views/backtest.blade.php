@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col">
                <backtest-component></backtest-component>
            </div>
        </div>
    </div>
@endsection

@push('child-scripts')
    <script src="{{ asset('js/backtest.js') }}" defer></script>
@endpush
