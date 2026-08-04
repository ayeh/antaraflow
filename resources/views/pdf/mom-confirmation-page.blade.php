<div style="page-break-before: always; font-family: Arial, sans-serif; font-size: 11pt; padding: 30px;">
    <h2 style="text-align: center; font-size: 13pt; margin-bottom: 20px; letter-spacing: 1px;">
        PENGESAHAN MINIT MESYUARAT
    </h2>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 10pt;">
        <tr>
            <td style="width: 30%; padding: 4px 0;"><strong>Nombor Minit</strong></td>
            <td style="padding: 4px 0;">: {{ $meeting->mom_number ?? '-' }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 0;"><strong>Diedarkan</strong></td>
            <td style="padding: 4px 0;">: {{ $circulation->created_at->format('d F Y, g:i A') }} (Pusingan {{ $circulation->round }})</td>
        </tr>
        <tr>
            <td style="padding: 4px 0;"><strong>Tempoh</strong></td>
            <td style="padding: 4px 0;">: sehingga {{ $circulation->deadline_at->format('d F Y, g:i A') }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 0;"><strong>Penerima</strong></td>
            <td style="padding: 4px 0;">: {{ $circulation->recipients->count() }} orang</td>
        </tr>
    </table>

    <table style="width: 100%; border-collapse: collapse; font-size: 10pt; margin-bottom: 20px;">
        <thead>
            <tr style="background-color: #f5f5f5;">
                <th style="text-align: left; padding: 6px 8px; border: 1px solid #ddd;">Nama</th>
                <th style="text-align: left; padding: 6px 8px; border: 1px solid #ddd;">Cara pengesahan</th>
                <th style="text-align: left; padding: 6px 8px; border: 1px solid #ddd;">Tarikh</th>
            </tr>
        </thead>
        <tbody>
            @foreach($circulation->recipients as $recipient)
            <tr>
                <td style="padding: 6px 8px; border: 1px solid #ddd;">{{ $recipient->name }}</td>
                <td style="padding: 6px 8px; border: 1px solid #ddd;">
                    @if($recipient->response === 'confirmed')
                        Disahkan secara nyata
                    @elseif($recipient->deemed_confirmed_at)
                        Tiada maklum balas (dianggap sah)
                    @else
                        -
                    @endif
                </td>
                <td style="padding: 6px 8px; border: 1px solid #ddd;">
                    {{ ($recipient->responded_at ?? $recipient->deemed_confirmed_at)?->format('d M Y, g:i A') ?? '-' }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <p style="font-size: 10pt; color: #555; border-top: 1px solid #e5e5e5; padding-top: 12px;">
        Dokumen ini boleh disahkan kesahihannya di:
        <strong>{{ config('app.url') }}/mom/verify/{{ $meeting->id }}</strong>
    </p>
</div>
