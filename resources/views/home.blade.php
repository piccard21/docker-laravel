@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col">
                <div class="card">
                    <div class="card-header">{{ __('Dashboard') }}</div>

                    <div class="card-body">
                        @if (session('status'))
                            <div class="alert alert-success" role="alert">
                                {{ session('status') }}
                            </div>
                        @endif

                        <table class="table">
                            <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Symbol</th>
                                <th scope="col">Timeframe</th>
                                <th scope="col">Status</th>
                                <th scope="col">Next</th>
                                <th scope="col">LastTimeTriggered</th>
                                <th scope="col">StartQuote</th>
                                <th scope="col">Base</th>
                                <th scope="col">Quote</th>
                                <th scope="col">ROI</th>
                                <th scope="col">Info</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($jobs as $job)

                                <tr>
                                    <td>{{ $job["id"] }}</td>
                                    <td>{{ $job["symbol"] }}</td>
                                    <td>{{ $job["timeframe"] }}</td>
                                    <td class="@if($job["status"] === 'ACTIVE') table-success @endif">{{ $job["status"] }}</td>
                                    <td>{{ $job["next"] }}</td>
                                    <td>{{ $job["lastTimeTriggered"] }}</td>
                                    <td>{{ $job["start_price"] }}</td>
                                    <td>{{ $job["base"] }}</td>
                                    <td>{{ $job["quote"] }}</td>
                                    <td class="@if($job["roi"] >= 0) table-success @else table-danger @endif">{{ $job["roi"] }}%</td>
                                    <td><a href="{{route('job-info', $job['id'])}}" class="btn btn-primary btn-sm active">info</a></td>
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
