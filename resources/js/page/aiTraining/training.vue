<template>
    <div class="modal-detect">
        <div class="modal-header">
            <h3>Detect Serial Numbers</h3>
            <button class="close" @click="$emit('close')">&times;</button>
        </div>
        <div class="modal-body">
            <p>Enter the serial numbers for the items you want to receive:</p>
            <textarea v-model="serialNumbers" placeholder="Enter serial numbers, one per line"></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" @click="submitSerialNumbers">Submit</button>
            <button class="btn btn-secondary" @click="$emit('close')">Cancel</button>
        </div>
    </div>
</template>
<script>
export default {
    name: 'DetectSerialModal',
    data() {
        return {
            serialNumbers: ''
        };
    },
    methods: {
        submitSerialNumbers() {
            if (this.serialNumbers.trim() === '') {
                alert('Please enter at least one serial number.');
                return;
            }
            // Emit the serial numbers to the parent component
            this.$emit('serial-numbers-submitted', this.serialNumbers.split('\n').map(num => num.trim()));
            this.serialNumbers = ''; // Clear the input
            this.$emit('close'); // Close the modal
        }
    }
};
</script>
          