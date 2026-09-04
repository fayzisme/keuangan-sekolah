<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Keuangan Siswa</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { margin: 0; font-size: 18px; text-transform: uppercase; }
        .header p { margin: 2px 0; font-size: 11px; color: #666; }
        .info-table, .data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table td { padding: 4px; vertical-align: top; }
        .data-table th, .data-table td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        .data-table th { background-color: #f2f2f2; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .badge { padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .badge-paid { background: #d1fae5; color: #065f46; }
        .badge-partial { background: #fef3c7; color: #92400e; }
        .badge-open { background: #fee2e2; color: #991b1b; }
        .summary-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; margin-bottom: 20px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $school_name }}</h1>
        <p>Laporan Rincian Keuangan Siswa</p>
    </div>

    <table class="info-table">
        <tr>
            <td width="15%"><strong>Nama Siswa</strong></td>
            <td width="35%">: {{ $student_name }}</td>
            <td width="15%"><strong>NIS</strong></td>
            <td width="35%">: {{ $nis }}</td>
        </tr>
        <tr>
            <td><strong>Kelas</strong></td>
            <td>: {{ $class_name }}</td>
            <td><strong>Tanggal Cetak</strong></td>
            <td>: {{ date('d/m/Y H:i') }}</td>
        </tr>
    </table>

    <div class="summary-box">
        <table width="100%">
            <tr>
                <td>Total Tagihan: <strong>Rp {{ number_format($total_tagihan_cents / 100, 0, ',', '.') }}</strong></td>
                <td>Total Dibayar: <strong>Rp {{ number_format($total_dibayar_cents / 100, 0, ',', '.') }}</strong></td>
                <td>Sisa Tunggakan: <strong style="color: #dc2626;">Rp {{ number_format($sisa_cents / 100, 0, ',', '.') }}</strong></td>
            </tr>
        </table>
    </div>

    <h3>Rincian Tagihan</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th width="5%" class="text-center">No</th>
                <th>Jenis Tagihan</th>
                <th class="text-center">Periode</th>
                <th class="text-right">Nominal</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoices as $index => $inv)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $inv['bill_type'] }}</td>
                <td class="text-center">{{ $inv['periode'] }}</td>
                <td class="text-right">Rp {{ number_format($inv['amount_cents'] / 100, 0, ',', '.') }}</td>
                <td class="text-center">
                    <span class="badge badge-{{ strtolower($inv['status']) }}">{{ $inv['status'] }}</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
