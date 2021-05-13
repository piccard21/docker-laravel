@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col">
                <div class="card">
                    <div class="card-header">{{ $job->symbol  }} - {{ $job->timeframe }}</div>

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
                                    <td>{{ $log["message"] }}</td>
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
