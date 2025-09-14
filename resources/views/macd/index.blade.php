<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('MACD Strategy') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">

                    <form method="GET" action="{{ route('futures.macd_strategy') }}" class="mb-4">
                        <label for="market" class="mr-2">Select a market to compare:</label>
                        <select name="market" id="market" class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                            @foreach($markets as $market)
                                <option value="{{ $market }}" {{ $selectedMarket == $market ? 'selected' : '' }}>
                                    {{ $market }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="ml-2 px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                            Compare
                        </button>
                    </form>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Timeframe
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Market
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Normalized MACD
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Normalized Signal
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($timeframes as $timeframe)
                                    @foreach(['BTCUSDT', 'ETHUSDT', $selectedMarket] as $market)
                                        @if(isset($macdData[$timeframe][$market]))
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">{{ $timeframe }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap">{{ $market }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap">{{ number_format($macdData[$timeframe][$market]['normalized_macd'], 4) }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap">{{ number_format($macdData[$timeframe][$market]['normalized_signal'], 4) }}</td>
                                            </tr>
                                        @else
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">{{ $timeframe }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap">{{ $market }}</td>
                                                <td colspan="2" class="px-6 py-4 whitespace-nowrap text-red-500">Not enough data</td>
                                            </tr>
                                        @endif
                                    @endforeach
                                    <tr class="bg-gray-100">
                                        <td colspan="4" class="px-6 py-1"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
