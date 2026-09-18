$url = 'http://wanawan_project.test/api/superadmin_process_refund.php';
$data = @{
    return_id = 1
    refund_note = 'test'
};
$response = Invoke-WebRequest -Uri $url -Method Post -Body $data -SkipHttpErrorCheck
Write-Host "Status: $($response.StatusCode)"
Write-Host "Content: $($response.Content)"
