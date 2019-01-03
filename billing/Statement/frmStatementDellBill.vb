Public Class frmStatementDellBill
    Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property
    Private Sub frmStatementDellBill_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        If (e.KeyCode = Keys.Back) Then
            Dim result As Integer = MessageBox.Show("Are you sure you want to delete this Bill " + txtbillno.Text + "?", "Caution!", MessageBoxButtons.YesNo)
            If result = DialogResult.Yes Then
                DelStatementItem()
                DeleteStatementBill()
                ViewStatement()
            End If
        ElseIf (e.KeyCode = Keys.Escape) Then
            frmStatement.txtNotice.Text = ""
            Me.Dispose()
        End If

    End Sub

    Private Sub frmStatementDellBill_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        Me.KeyPreview = True
        DeleteStatementBill()
    End Sub

    Private Sub dgStatementDel_CellContentClick(sender As Object, e As DataGridViewCellEventArgs) Handles dgStatementDel.CellContentClick

    End Sub

    Private Sub dgStatementDel_SelectionChanged(sender As Object, e As EventArgs) Handles dgStatementDel.SelectionChanged
        On Error Resume Next
        txtItemid.Text = dgStatementDel.CurrentRow.Cells(0).Value.ToString
        txtbillno.Text = dgStatementDel.CurrentRow.Cells(2).Value.ToString
    End Sub
End Class