<!DOCTYPE html>
<html>
<head>
    <title>GPS API 測試</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <h1>GPS API 測試工具</h1>
    
    <div style="margin: 20px;">
        <h3>發送測試 GPS 資料</h3>
        <form id="gpsForm">
            <label>User ID:</label>
            <input type="number" id="user_id" value="2" required><br><br>
            
            <label>緯度:</label>
            <input type="number" id="latitude" value="25.0330" step="any" required><br><br>
            
            <label>經度:</label>
            <input type="number" id="longitude" value="121.5654" step="any" required><br><br>
            
            <label>精度 (公尺):</label>
            <input type="number" id="accuracy" value="10" step="any"><br><br>
            
            <label>速度 (km/h):</label>
            <input type="number" id="speed" value="0" step="any"><br><br>
            
            <button type="submit">發送 GPS 資料</button>
        </form>
        
        <div id="result" style="margin-top: 20px; padding: 10px; border: 1px solid #ccc;"></div>
    </div>

    <script>
        $('#gpsForm').on('submit', function(e) {
            e.preventDefault();
            
            const data = {
                user_id: $('#user_id').val(),
                latitude: parseFloat($('#latitude').val()),
                longitude: parseFloat($('#longitude').val()),
                accuracy: parseFloat($('#accuracy').val()),
                speed: parseFloat($('#speed').val()),
                recorded_at: new Date().toISOString()
            };
            
            $.ajax({
                url: '/api/gps',
                method: 'POST',
                data: data,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $('#result').html('<div style="color: green;"><strong>成功:</strong><br>' + 
                        JSON.stringify(response, null, 2) + '</div>');
                },
                error: function(xhr) {
                    $('#result').html('<div style="color: red;"><strong>錯誤:</strong><br>' + 
                        JSON.stringify(xhr.responseJSON, null, 2) + '</div>');
                }
            });
        });
    </script>
</body>
</html>