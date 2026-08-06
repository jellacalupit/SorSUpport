<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Analytics Dashboard
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="GET" action="{{ route('admin.analytics.index') }}" class="grid gap-4 sm:grid-cols-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Start Date</label>
                        <input type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">End Date</label>
                        <input type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Category</label>
                        <select name="category_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All categories</option>
                            @foreach ($categoryOptions as $category)
                                <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? '') == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">Filter</button>
                        <a href="{{ route('admin.analytics.index') }}" class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">Reset</a>
                    </div>
                </form>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-500">Total Complaints</h3>
                    <p class="mt-4 text-3xl font-bold text-gray-900">{{ $totalComplaints }}</p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-500">Total Tickets</h3>
                    <p class="mt-4 text-3xl font-bold text-gray-900">{{ $totalTickets }}</p>
                </div>
                @foreach ($statusCounts as $status => $count)
                    <div class="bg-white p-6 rounded-lg shadow-sm">
                        <h3 class="text-sm font-semibold text-gray-500">{{ ucfirst(str_replace('_', ' ', $status)) }}</h3>
                        <p class="mt-4 text-3xl font-bold text-gray-900">{{ $count }}</p>
                    </div>
                @endforeach
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-800">Complaint Volume (Daily)</h3>
                    <canvas id="complaintVolumeDaily"></canvas>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-800">Complaint Volume (Weekly)</h3>
                    <canvas id="complaintVolumeWeekly"></canvas>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-800">Complaint Volume (Monthly)</h3>
                    <canvas id="complaintVolumeMonthly"></canvas>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-800">Complaint Distribution by Category</h3>
                    <canvas id="categoryDistribution"></canvas>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-800">Resolution Rate</h3>
                    <canvas id="resolutionRate"></canvas>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-800">Average Resolution Time</h3>
                    <canvas id="averageResolutionTime"></canvas>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-800">Escalation Frequency</h3>
                    <canvas id="escalationFrequency"></canvas>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-800">Active Ticket Statuses</h3>
                    <canvas id="activeTicketStatuses"></canvas>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <h3 class="text-lg font-semibold text-gray-800">Report Exports</h3>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('admin.analytics.export.pdf', request()->query()) }}" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Download PDF</a>
                        <a href="{{ route('admin.analytics.export.excel', request()->query()) }}" class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">Download Excel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const charts = [
            { id: 'complaintVolumeDaily', data: @json($complaintVolumeDaily), label: 'Daily Volume', color: 'rgba(59, 130, 246, 0.7)' },
            { id: 'complaintVolumeWeekly', data: @json($complaintVolumeWeekly), label: 'Weekly Volume', color: 'rgba(16, 185, 129, 0.7)' },
            { id: 'complaintVolumeMonthly', data: @json($complaintVolumeMonthly), label: 'Monthly Volume', color: 'rgba(251, 191, 36, 0.7)' },
            { id: 'categoryDistribution', data: @json($categoryDistribution), label: 'Distribution', color: 'rgba(236, 72, 153, 0.7)', type: 'doughnut' },
            { id: 'resolutionRate', data: @json($resolutionRate['chart']), label: 'Resolution Rate', color: 'rgba(14, 165, 233, 0.7)' },
            { id: 'averageResolutionTime', data: @json($averageResolutionTime), label: 'Avg Resolution Time', color: 'rgba(249, 115, 22, 0.7)' },
            { id: 'escalationFrequency', data: @json($escalationFrequency), label: 'Escalation Count', color: 'rgba(109, 40, 217, 0.7)' },
            { id: 'activeTicketStatuses', data: @json($activeTicketStatuses), label: 'Status Distribution', color: 'rgba(20, 184, 166, 0.7)', type: 'doughnut' },
        ];

        charts.forEach(({ id, data, label, color, type }) => {
            const ctx = document.getElementById(id);
            if (!ctx) {
                return;
            }

            new Chart(ctx, {
                type: type ?? 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label,
                        data: data.data,
                        backgroundColor: Array(data.data.length).fill(color),
                        borderColor: Array(data.data.length).fill(color),
                        borderWidth: 1,
                    }],
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                        },
                    },
                },
            });
        });
    </script>
</x-app-layout>
