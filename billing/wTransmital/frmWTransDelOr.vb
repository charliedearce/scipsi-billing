Public Class frmWTransDelOr

    Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property

    Private Sub dgWtransDel_SelectionChanged(sender As Object, e As EventArgs) Handles dgWtransDel.SelectionChanged
        On Error Resume Next
        txtItemid.Text = dgWtransDel.CurrentRow.Cells(0).Value.ToString
        txtbillno.Text = dgWtransDel.CurrentRow.Cells(1).Value.ToString
    End Sub

    Private Sub frmWTransDelbill_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        If (e.KeyCode = Keys.Back) Then
            Dim result As Integer = MessageBox.Show("Are you sure you want to delete this OR# " + txtbillno.Text + "?", "Caution!", MessageBoxButtons.YesNo)
            If result = DialogResult.Yes Then
                DelWtranstItem()
                ViewWTransDel()
                ViewWTrans()
            End If
        ElseIf (e.KeyCode = Keys.Escape) Then
            frmWtrans.txtNotice.Text = ""
            Me.Dispose()
        End If
    End Sub

    Private Sub frmWTransDelbill_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        ViewWTransDel()
        Me.KeyPreview = True
    End Sub
End Class