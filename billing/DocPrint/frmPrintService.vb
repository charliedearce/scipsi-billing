Public Class frmPrintService
    Public Property BillNumber As String

    Private Sub frmPrintService_Load(ByVal sender As Object, ByVal e As EventArgs) Handles MyBase.Load
        Dim numberToPrint As String = BillNumber
        If String.IsNullOrWhiteSpace(numberToPrint) Then
            numberToPrint = frmBilling.txtBillno.Text
        End If

        ServicePrintTemplateService.PrintBill(numberToPrint, Me)
        Me.Dispose()
    End Sub
End Class
