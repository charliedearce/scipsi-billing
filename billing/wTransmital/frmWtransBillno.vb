Public Class frmWtransOrno
    Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property
    Private Sub frmWtransOrno_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        If (e.KeyCode = Keys.Enter) Then
            If txtOrnum.Text = "" Then
                MsgBox("Please Enter BILL number.", vbExclamation, Title:="Message")
            Else
                ViewWTrans()
                additemWhiteTransmittal()
                ViewWTrans()
            End If
        ElseIf (e.KeyCode = Keys.Escape) Then
            frmStatement.txtNotice.Text = ""
            Me.Dispose()
        End If
    End Sub



    Private Sub frmWtransOrno_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        Me.KeyPreview = True
    End Sub

    Private Sub txtOrnum_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtOrnum.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtOrnum_TextChanged(sender As Object, e As EventArgs) Handles txtOrnum.TextChanged

    End Sub
End Class