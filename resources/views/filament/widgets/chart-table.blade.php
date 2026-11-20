<table>
    <caption>{{ $caption }}</caption>
    <thead>
        <tr>
            <td></td>
            @foreach ($data['datasets'] as $dataset)
                <th scope="col">{{ $dataset['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($data['labels'] as $index => $label)
            <tr>
                <th scope="row">{{ $label }}</th>
                @foreach ($data['datasets'] as $dataset)
                    <td>{{ $dataset['data'][$index] ?? '' }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
