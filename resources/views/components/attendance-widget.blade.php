<!-- resources/views/components/attendance-widget.blade.php -->
<style>
.attendance-widget {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
            width:300px;
        }

        .attendance-widget .workplace {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .attendance-widget .clock-in-out {
            display: flex;
            justify-content: space-between;
        }

        .attendance-widget .clock-in-out div {
            text-align: center;
        }
    </style>
    
<div class="attendance-widget">
    <h4>Attendance</h4>
    <p>Shift duration: <strong>8 hours</strong></p>
    <div class="clock-in-out">
        <div>
            <p>8:58 am</p>
            <button class="btn btn-primary">Clock In</button>
        </div>
        <div>
            <p>1:30 pm</p>
            <button class="btn btn-secondary">Clock Out</button>
        </div>
    </div>
</div>
