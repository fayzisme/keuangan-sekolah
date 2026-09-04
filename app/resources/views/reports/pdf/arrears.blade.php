<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Tunggakan Siswa</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; margin: 15px; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #333; padding-bottom: 8px; }
        .header h1 { margin: 0; font-size: 16px; text-transform: uppercase; }
        .header p { margin: 2px 0; font-size: 10px; color: #666; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th, .data-table td { border: 1px solid #ccc; padding: 5px; text-align: left; }
        .data-table th { background-color: #f2f2f2; font-weight: bold; font-size: 10px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row { font-weight: bold; background-color: #f8fafc; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $school_name }}</h1>
        <p>Laporan Rekap Tunggakan Pembayaran Siswa</p>
        <p>Per Tanggal: {{ date('d/m/Y') }}</p>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="4%" class="text-center">No</th>
                <th width="12%">NIS</th>
                <th width="22%">Nama Siswa</th>
                <th width="12%">Kelas</th>
                <th width="18%">Tagihan</th>
                <th width="10%" class="text-center">Periode</th>
                <th width="11%" class="text-right">Tagihan</th>
                <th width="11%" class="text-right">Sisa</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $index => $row)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $row['nis'] }}</td>
                <td>{{ $row['nama'] }}</td>
                <td>{{ $row['kelas'] }}</td>
                <td>{{ $row['bill_type'] }}</td>
                <td class="text-center">{{ $row['periode'] }}</td>
                <td class="text-right">Rp {{ number_format($row['tagihan_cents'] / 100, 0, ',', '.') }}</td>
                <td class="text-right" style="color: #dc2626;">Rp {{ number_format($row['sisa_cents'] / 100, 0, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="7" class="text-right">TOTAL TUNGGAKAN:</td>
                <td class="text-right" style="color: #dc2626;">Rp {{ number_format($total_tunggakan_cents / 100, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
