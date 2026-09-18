$url = 'http://wanawan_project.test/api/superadmin_process_refund.php';
$data = @{
    return_id = 1
    refund_note = 'test'
};
try {
    $response = Invoke-WebRequest -Uri $url -Method Post -Body $data
    Write-Host "Status: $($response.StatusCode)"
    Write-Host "Content: $($response.Content)"
} catch {
    Write-Host "Exception: $_"
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $reader.BaseStream.Position = 0
        $reader.DiscardBufferedData()
        $responseBody = $reader.ReadToEnd()
        Write-Host "Response Body: $responseBody"
    }
}
