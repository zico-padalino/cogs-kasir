@extends('layouts.admin')

@section('title', 'Beranda Admin')
@section('heading', 'Beranda Admin')
@section('subheading', 'Ringkasan penjualan dari kasir')

@section('content')
    @include('shared.sales-report', [
        'filterAction' => route('admin.dashboard'),
        'showOrderList' => false,
    ])
@endsection
