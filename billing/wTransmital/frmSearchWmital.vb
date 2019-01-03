Public Class frmSearchWmital
    Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property
    Private Sub frmSearchWmital_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        If (e.KeyCode = Keys.Enter) Then
            SearchWmital()
            ViewWTrans()
        ElseIf (e.KeyCode = Keys.Escape) Then
            Me.Dispose()
            frmYtrans.txtindi.Text = 1
        End If
    End Sub

    Private Sub frmSearchWmital_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        Me.KeyPreview = True
    End Sub
End Class