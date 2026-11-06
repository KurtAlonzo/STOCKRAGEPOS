@extends('layouts.main')

@section('title', 'Dashboard | STOCKRAGE')

@section('content')

    <div class="page-header">
        <h1>Dashboard</h1>
        <p>Welcome to STOCKRAGE POS & Inventory Management System.</p>
    </div>

    <div class="dashboard-grid">

        <div class="dashboard-card">
            <h3>Today's Sales</h3>
            <p class="card-value">₱2,450</p>
        </div>

        <div class="dashboard-card">
            <h3>Monthly Revenue</h3>
            <p class="card-value">₱48,500</p>
        </div>

        <div class="dashboard-card">
            <h3>Total Orders</h3>
            <p class="card-value">320</p>
        </div>

        <div class="dashboard-card">
            <h3>Low Stock Items</h3>
            <p class="card-value warning">3 Items</p>
        </div>

    </div>

    <p class="sample-note">
        Sample dashboard data for UI preview only.
    </p>

@endsection