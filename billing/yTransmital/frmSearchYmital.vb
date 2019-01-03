Public Class frmSearchYmital
    Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property
    Private Sub frmSearchYmital_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        If (e.KeyCode = Keys.Enter) Then
            SearchYmital()
            ViewYTrans()
        ElseIf (e.KeyCode = Keys.Escape) Then
            Me.Dispose()
            frmYtrans.txtindi.Text = 1
        End If
    End Sub

    Private Sub frmSearchYmital_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        Me.KeyPreview = True
    End Sub

    Private Sub txtYmitalno_MaskInputRejected(sender As Object, e As MaskInputRejectedEventArgs) Handles txtYmitalno.MaskInputRejected

    End Sub
End Class