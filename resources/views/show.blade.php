@extends('layouts.app')

@section('content')
    <div class="container">

        {{--CHART--}}
        @if($job->status === 'ACTIVE' || $job->status === 'INACTIVE')
            <div class="row justify-content-center">
                <div class="col">
                    <chart-component :chart-data="{{$chart}}"></chart-component>
                </div>
            </div>
        @endif

        {{--LOGS--}}
        <div class="row justify-content-center">
            <div class="col">
                <div class="card">
                    <div class="card-header">{{ $job->symbol  }} - {{ $job->timeframe }} - {{ $job->settings['ema1'] }}/{{ $job->settings['ema2'] }}</div>

                    <div class="card-body">
                        <table class="table">
                            <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Method</th>
                                <th scope="col">Type</th>
                                <th scope="col">Message</th>
                                <th scope="col">time</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($logs as $log)
                                <tr>
                                    <td>{{ $log["id"] }}</td>
                                    <td>{{ $log["method"] }}</td>
                                    <td class="@if($log["type"] === 'SUCCESS') table-success @elseif ($log["type"] === 'INFO') table-primary @elseif ($log["type"] === 'WARNING') table-warning @else table-danger @endif">{{ $log["type"] }}</td>
                                    <td>
                                        <button type="button" class="btn btn-primary btn-sm">info</button>
                                    </td>
                                    <td>{{ $log["time"] }}</td>
                                </tr>
                            @endforeach

                            </tbody>
                        </table>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('child-scripts')
    <script src="{{ asset('js/job.js') }}" defer></script>
@endpush
