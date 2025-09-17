<div>
    <div class="row">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">今日碳排放</h5>
                    <h2 class="text-primary">{{ number_format($todayEmission, 2) }} kg</h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">本週碳排放</h5>
                    <h2 class="text-warning">{{ number_format($weekEmission, 2) }} kg</h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">本月碳排放</h5>
                    <h2 class="text-danger">{{ number_format($monthEmission, 2) }} kg</h2>
                </div>
            </div>
        </div>
    </div>
</div>