Public Class frmortrans
    Dim ornumber As String
    Dim type As String
    Dim myid As String
    Dim status As String
    Dim reqby As String
    Dim reason As String
    Private Sub txtornum_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtornum.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtornum_TextChanged(sender As Object, e As EventArgs) Handles txtornum.TextChanged
        searchortrans()
        viewortrans()
    End Sub

    Private Sub frmortrans_FormClosed(sender As Object, e As FormClosedEventArgs) Handles Me.FormClosed
        Me.Dispose()
    End Sub

    Private Sub frmortrans_Load(sender As Object, e As EventArgs) Handles MyBase.Load


        orbank()
        If (myCon.State = ConnectionState.Open) Then
            myCon.close()
        End If
        vieworrequest()
    End Sub

    Private Sub txtAccountno_MaskInputRejected(sender As Object, e As MaskInputRejectedEventArgs) Handles txtAccountno.MaskInputRejected

    End Sub

    Private Sub txtAccountno_TextChanged(sender As Object, e As EventArgs) Handles txtAccountno.TextChanged
        client(txtAccountno.Text)
    End Sub

    Private Sub btn2_Click(sender As Object, e As EventArgs) Handles btn2.Click
        deleteortrans()
    End Sub

    Private Sub btn1_Click(sender As Object, e As EventArgs) Handles btn1.Click
        Dim result As Integer = MessageBox.Show("Are you sure you want to Update this OR #: " + txtornum.Text + "?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            orvalidation()
        End If
    End Sub

    Private Sub chkper_CheckedChanged(sender As Object, e As EventArgs) Handles chkper.CheckedChanged
        If chkper.Checked = True Then
            txtperiod.ReadOnly = False
        Else
            txtperiod.ReadOnly = True
        End If
    End Sub

   
    Private Sub DataGridView1_CellContentClick(sender As Object, e As DataGridViewCellEventArgs) Handles DataGridView1.CellContentClick

    End Sub

    Private Sub DataGridView1_CellMouseClick(sender As Object, e As DataGridViewCellMouseEventArgs) Handles DataGridView1.CellMouseClick
        On Error Resume Next
        Dim myindex As Integer = DataGridView1.CurrentRow.Index
        myid = DataGridView1.Item(0, myindex).Value
        txtornum.Text = DataGridView1.Item(1, myindex).Value
        ornumber = DataGridView1.Item(1, myindex).Value
        type = DataGridView1.Item(2, myindex).Value
        reason = DataGridView1.Item(3, myindex).Value
        reqby = DataGridView1.Item(4, myindex).Value
        status = DataGridView1.Item(5, myindex).Value
    End Sub

    Private Sub SimpleButton1_Click(sender As Object, e As EventArgs) Handles SimpleButton1.Click
        vieworrequest()
    End Sub

    Private Sub SimpleButton2_Click(sender As Object, e As EventArgs) Handles SimpleButton2.Click
        If ornumber = "" Or type = "" Then
            MsgBox("Select request to process.")
        ElseIf status = "Processed" Then
            MsgBox("This request is already processed.")
        Else
            Dim result As Integer = MessageBox.Show(reqby + " is requesting to " + type + " this OR #: " + ornumber + " because of the following reasons: " + reason, "Caution!", MessageBoxButtons.YesNo)
            If result = DialogResult.Yes Then
                processorrequest(myid, ornumber, type)
                vieworrequest()
            End If
        End If
    End Sub
End Class