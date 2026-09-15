Public Class frmPrintOR
    Public Property ReceiptNumber As String

    Private Sub frmPrintOR_Load(ByVal sender As Object, ByVal e As EventArgs) Handles MyBase.Load
        Dim numberToPrint As String = ReceiptNumber
        If String.IsNullOrWhiteSpace(numberToPrint) Then
            numberToPrint = frmor.txtOrno.Text
        End If

        OrPrintTemplateService.PrintReceipt(numberToPrint, Me)
        Me.Dispose()
    End Sub
End Class
