Public Class frmedithtax
    Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property

    Private Sub txtHtax_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtHtax.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub
    Private Sub txtORno_TextChanged(sender As Object, e As EventArgs) Handles txtHtax.TextChanged

    End Sub

    Private Sub frmedithtax_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        If (e.KeyCode = Keys.Enter) Then
            If txtHtax.Text = "" Then
                frmWtrans.txtNotice.Text = "Please enter W/htax amount."
            Else
                Updatehtax()
                ViewWTrans()
                Me.Dispose()
            End If

        ElseIf (e.KeyCode = Keys.Escape) Then
            Me.Dispose()
        End If
    End Sub

    Private Sub frmedithtax_KeyPress(sender As Object, e As KeyPressEventArgs) Handles Me.KeyPress

    End Sub

    Private Sub frmedithtax_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        Me.KeyPreview = True
    End Sub
End Class