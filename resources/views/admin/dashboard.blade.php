@extends('layouts.admin')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('subheading', 'Ringkasan penjualan dari kasir')

@section('content')
    @include('shared.sales-report', [
        'filterAction' => route('admin.dashboard'),
        'showOrderList' => false,
    ])
@endsection
